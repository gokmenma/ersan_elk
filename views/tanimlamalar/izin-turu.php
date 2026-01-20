<?php

require_once dirname(__DIR__, 2) . '/Autoloader.php';
use App\Helper\Security;
use App\Helper\Form;

use App\Model\TanimlamalarModel;
$Tanimlamalar = new TanimlamalarModel();

$izinTurleri = $Tanimlamalar->getIzinTurleri();

?>

<div class="container-fluid">

    <!-- start page title -->
    <?php
    $maintitle = "Ana Sayfa";
    $title = "İzin Türü Tanımlamaları";
    ?>
    <?php include 'layouts/breadcrumb.php'; ?>
    <!-- end page title -->

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-grid d-md-flex d-block">
                    <div class="card-title col-md-8">

                        <h4 class="card-title">İzin Türleri Listesi</h4>
                        <p class="card-title-desc">İzin türlerini görüntüleyebilir ve yeni izin türü
                            ekleyebilirsiniz. Bu türler personel izin taleplerinde kullanılacaktır.
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
                                <th class="text-center">İzin Türü</th>
                                <th class="text-center" style="width:12%">Ücret Durumu</th>
                                <th class="text-center" style="width:12%">Personel Görebilir</th>
                                <th class="text-center">Açıklama</th>
                                <th style="width:5%">İşlem</th>
                            </tr>
                        </thead>


                        <tbody>

                            <?php
                            $i = 0;
                            foreach ($izinTurleri as $izinTuru) {
                                $i++;
                                $enc_id = Security::encrypt($izinTuru->id);

                                // Ücret durumu badge
                                $ucretBadge = $izinTuru->ucretli_mi == 1
                                    ? '<span class="badge bg-success"><i class="bx bx-check me-1"></i>Ücretli</span>'
                                    : '<span class="badge bg-danger"><i class="bx bx-x me-1"></i>Ücretsiz</span>';

                                // Personel görebilir badge
                                $gorebilirBadge = $izinTuru->personel_gorebilir == 1
                                    ? '<span class="badge bg-info"><i class="bx bx-show me-1"></i>Evet</span>'
                                    : '<span class="badge bg-secondary"><i class="bx bx-hide me-1"></i>Hayır</span>';
                                ?>
                                <tr id="row_<?php echo $izinTuru->id; ?>">
                                    <td class="text-center">
                                        <?php echo $izinTuru->id ?>
                                    </td>
                                    <td class="text-center">
                                        <strong>
                                            <?php echo $izinTuru->tur_adi ?>
                                        </strong>
                                    </td>
                                    <td class="text-center">
                                        <?php echo $ucretBadge ?>
                                    </td>
                                    <td class="text-center">
                                        <?php echo $gorebilirBadge ?>
                                    </td>
                                    <td class="text-center">
                                        <?php echo $izinTuru->aciklama ?>
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
                <h5 class="modal-title" id="actionModalLabel"><i class="bx bx-calendar-check me-2"></i>İzin Türü
                    İşlemleri</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                    aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="actionForm">
                    <input type="hidden" name="izin_turu_id" id="izin_turu_id" class="form-control" value="0">

                    <div class="row mb-3">

                        <div class="col-md-12">
                            <?php echo
                                Form::FormFloatInput(
                                    "text",
                                    "izin_turu",
                                    "",
                                    "İzin Türü giriniz!",
                                    "İzin Türü",
                                    "calendar",
                                    "form-control"

                                ); ?>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="card border mb-0">
                                <div class="card-body p-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="ucretli_mi"
                                            name="ucretli_mi" checked>
                                        <label class="form-check-label fw-medium" for="ucretli_mi">
                                            <i class="bx bx-money text-success me-1"></i>Ücretli İzin
                                        </label>
                                    </div>
                                    <small class="text-muted d-block mt-1">İzin maaştan kesilmeyecek</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card border mb-0">
                                <div class="card-body p-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="personel_gorebilir"
                                            name="personel_gorebilir" checked>
                                        <label class="form-check-label fw-medium" for="personel_gorebilir">
                                            <i class="bx bx-show text-info me-1"></i>Personel Görebilir
                                        </label>
                                    </div>
                                    <small class="text-muted d-block mt-1">İzin talebinde görünür</small>
                                </div>
                            </div>
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
                                    "file-text",


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

<script src="views/tanimlamalar/js/izin-turu.js"></script>