<?php

require_once dirname(__DIR__, 2) . '/Autoloader.php';
use App\Helper\Security;
use App\Helper\Form;

use App\Model\TanimlamalarModel;
$Tanimlamalar = new TanimlamalarModel();

$izinTurleri = $Tanimlamalar->getIzinTurleri();

?>

<!-- Material Icons -->
<link rel="stylesheet"
    href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" />

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
                                <th class="text-center" style="width:5%">Kısa Kod</th>
                                <th class="text-center">Görünüm (PWA)</th>
                                <th class="text-center" style="width:12%">Ücret Durumu</th>
                                <th class="text-center" style="width:12%">Puantajda "X" (Çalışıyor) Say</th>
                                <th class="text-center" style="width:12%">Personel Görebilir</th>
                                <th class="text-center" style="width:12%">Yetkili Onayı</th>
                                <th class="text-center">Açıklama</th>
                                <th style="width:5%">İşlem</th>
                            </tr>
                        </thead>


                        <tbody>

                            <?php
                            // Tailwind renklerini CSS stillerine çeviren fonksiyon
                            function getStyleFromTailwind($tailwindClass)
                            {
                                if (strpos($tailwindClass, 'blue') !== false)
                                    return 'background-color: #dbeafe; color: #2563eb;';
                                if (strpos($tailwindClass, 'amber') !== false)
                                    return 'background-color: #fef3c7; color: #d97706;';
                                if (strpos($tailwindClass, 'red') !== false)
                                    return 'background-color: #fee2e2; color: #dc2626;';
                                if (strpos($tailwindClass, 'pink') !== false)
                                    return 'background-color: #fce7f3; color: #db2777;';
                                if (strpos($tailwindClass, 'gray') !== false)
                                    return 'background-color: #f3f4f6; color: #4b5563;';
                                if (strpos($tailwindClass, 'green') !== false)
                                    return 'background-color: #dcfce7; color: #16a34a;';
                                if (strpos($tailwindClass, 'purple') !== false)
                                    return 'background-color: #f3e8ff; color: #9333ea;';
                                return 'background-color: rgba(85, 110, 230, 0.1); color: #556ee6;'; // Varsayılan
                            }

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

                                // Yetkili onayına tabi badge
                                $onayBadge = isset($izinTuru->yetkili_onayina_tabi) && $izinTuru->yetkili_onayina_tabi == 1
                                    ? '<span class="badge bg-warning"><i class="bx bx-lock-alt me-1"></i>Evet</span>'
                                    : '<span class="badge bg-light text-dark"><i class="bx bx-lock-open-alt me-1"></i>Hayır</span>';

                                // Normal Mesai (Çalışıyor) Say
                                $mesaiBadge = isset($izinTuru->normal_mesai_sayilir) && $izinTuru->normal_mesai_sayilir == 1
                                    ? '<span class="badge bg-success"><i class="bx bx-check-double me-1"></i>Evet</span>'
                                    : '<span class="badge bg-secondary"><i class="bx bx-minus me-1"></i>Hayır</span>';


                                // Renk ve İkon önizleme
                                $renkClass = $izinTuru->renk ?? 'bg-primary/10 text-primary';
                                $ikonName = $izinTuru->ikon ?? 'event';
                                $inlineStyle = getStyleFromTailwind($renkClass);

                                $gorunumHtml = '
                                <div class="d-flex align-items-center justify-content-center">
                                    <div class="d-flex align-items-center justify-content-center rounded p-2" style="' . $inlineStyle . ' width: 40px; height: 40px;">
                                        <span class="material-symbols-outlined" style="font-size: 24px;">' . $ikonName . '</span>
                                    </div>
                                    <div class="ms-2 text-start">
                                        <small class="d-block text-muted" style="font-size: 10px;">' . $ikonName . '</small>
                                    </div>
                                </div>';
                                ?>
                                <tr id="row_<?php echo $izinTuru->id; ?>">
                                    <td class="text-center">
                                        <?php echo $izinTuru->id ?>
                                    </td>
                                    <td class="text-center">
                                        <strong><?php echo $izinTuru->tur_adi ?></strong>
                                    </td>
                                    <td class="text-center">
                                        <span
                                            class="badge badge-soft-dark border font-size-12 px-2"><?php echo $izinTuru->kisa_kod ?></span>
                                    </td>
                                    <td class="text-center">
                                        <?php echo $gorunumHtml; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php echo $ucretBadge ?>
                                    </td>
                                    <td class="text-center">
                                        <?php echo $mesaiBadge ?>
                                    </td>
                                    <td class="text-center">
                                        <?php echo $gorebilirBadge ?>
                                    </td>
                                    <td class="text-center">
                                        <?php echo $onayBadge ?>
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

                        <div class="col-md-8">
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
                        <div class="col-md-4">
                            <?php echo
                                Form::FormFloatInput(
                                    "text",
                                    "kisa_kod",
                                    "",
                                    "Kısa kod giriniz!",
                                    "Kısa Kod",
                                    "code",
                                    "form-control"

                                ); ?>
                        </div>
                    </div>


                    <div class="row mb-3">
                        <div class="col-md-6">
                            <?php echo
                                Form::FormSelect2(
                                    name: "renk",
                                    options: [
                                        "bg-primary/10 text-primary" => "Varsayılan (Mavi)",
                                        "bg-blue-100 dark:bg-blue-900/30 text-blue-600" => "Mavi (Yıllık)",
                                        "bg-amber-100 dark:bg-amber-900/30 text-amber-600" => "Turuncu (Mazeret)",
                                        "bg-red-100 dark:bg-red-900/30 text-red-600" => "Kırmızı (Hastalık)",
                                        "bg-pink-100 dark:bg-pink-900/30 text-pink-600" => "Pembe (Doğum)",
                                        "bg-gray-100 dark:bg-gray-900/30 text-gray-600" => "Gri (Ücretsiz)",
                                        "bg-green-100 dark:bg-green-900/30 text-green-600" => "Yeşil",
                                        "bg-purple-100 dark:bg-purple-900/30 text-purple-600" => "Mor",
                                    ],
                                    selectedValue: '',
                                    label: 'Renk',
                                    icon: 'columns',
                                    class: 'form-control select2'



                                ); ?>
                        </div>

                        <div class="col-md-6">
                            <?php echo
                                Form::FormSelect2(
                                    name: "ikon",
                                    options: [
                                        "event" => "Takvim (Varsayılan)",
                                        "beach_access" => "Plaj (Yıllık)",
                                        "event_note" => "Notlu Takvim (Mazeret)",
                                        "medical_services" => "Sağlık (Hastalık)",
                                        "child_friendly" => "Bebek (Doğum)",
                                        "money_off" => "Para Yok (Ücretsiz)",
                                        "flight" => "Uçak",
                                        "home" => "Ev",
                                        "work_off" => "İş Yok",
                                        "celebration" => "Kutlama",

                                    ],
                                    selectedValue: '',
                                    label: 'İkon',
                                    icon: 'home',
                                    class: 'form-control select2'



                                ); ?>
                        </div>



                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <div class="card border mb-0 h-100 shadow-none">
                                <div class="card-body p-3">
                                    <div class="form-check form-switch form-switch-md mb-2">
                                        <input class="form-check-input" type="checkbox" id="ucretli_mi"
                                            name="ucretli_mi" checked>
                                        <label class="form-check-label fw-medium d-flex align-items-center" for="ucretli_mi">
                                            <i class="bx bx-money text-success fs-5 me-2"></i>Ücretli İzin
                                        </label>
                                    </div>
                                    <small class="text-muted d-block ps-5">Maaştan kesilmez</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card border mb-0 h-100 shadow-none">
                                <div class="card-body p-3">
                                    <div class="form-check form-switch form-switch-md mb-2">
                                        <input class="form-check-input" type="checkbox" id="normal_mesai_sayilir"
                                            name="normal_mesai_sayilir">
                                        <label class="form-check-label fw-medium d-flex align-items-center" for="normal_mesai_sayilir">
                                            <i class="bx bx-briefcase text-primary fs-5 me-2"></i>Çalışıyor Görünsün
                                        </label>
                                    </div>
                                    <small class="text-muted d-block ps-5">İzinli listesinde çıkmaz</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card border mb-0 h-100 shadow-none">
                                <div class="card-body p-3">
                                    <div class="form-check form-switch form-switch-md mb-2">
                                        <input class="form-check-input" type="checkbox" id="personel_gorebilir"
                                            name="personel_gorebilir" checked>
                                        <label class="form-check-label fw-medium d-flex align-items-center" for="personel_gorebilir">
                                            <i class="bx bx-show text-info fs-5 me-2"></i>Personel Görebilir
                                        </label>
                                    </div>
                                    <small class="text-muted d-block ps-5">Talepte görünür</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card border mb-0 h-100 shadow-none">
                                <div class="card-body p-3">
                                    <div class="form-check form-switch form-switch-md mb-2">
                                        <input class="form-check-input" type="checkbox" id="yetkili_onayina_tabi"
                                            name="yetkili_onayina_tabi">
                                        <label class="form-check-label fw-medium d-flex align-items-center" for="yetkili_onayina_tabi">
                                            <i class="bx bx-lock-alt text-warning fs-5 me-2"></i>Yetkili Onayı
                                        </label>
                                    </div>
                                    <small class="text-muted d-block ps-5">Onay sonrası kilitlenir</small>
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

<script src="views/tanimlamalar/js/izin-turu.js?v=<?php echo time(); ?>"></script>