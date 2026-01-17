<?php

require_once dirname(__DIR__, 2) . '/Autoloader.php';

use App\Helper\City;
use App\Helper\Date;
use App\Helper\Helper;
use App\Helper\Route;
use App\Helper\Security;

$City = new City();

use App\Model\UyeIslemModel;
use App\Model\UyeModel;

$Uyeler = new UyeModel();
$UyeIslem = new UyeIslemModel();

$sube_id = $_SESSION['sube_id'];

if ($sube_id == 101) {
    $uyeler = $Uyeler->all()->orderBy("id", "desc")->get();
} else {
    $uyeler = $Uyeler->where("sube_id", $_SESSION['sube_id']);
}



?>
<style>
table th {
    cursor: pointer;
}
</style>

<div class="container-fluid">

    <!-- start page title -->
    <?php
    $maintitle = "Üyeler";
    $title = "Üye Listesi";
    ?>
    <?php include 'layouts/breadcrumb.php'; ?>
    <!-- end page title -->

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-grid d-md-flex d-block">
                    <div class="card-title col-md-8">

                        <h4 class="card-title">Üye Listesi</h4>
                        <p class="card-title-desc">Üyeleri görüntüleyebilir ve yeni üye ekleyebilirsiniz.
                        </p>
                    </div>

                    <div class="col-md-4">

                        <a href="<?php Route::get("uye/duzenle") ?>" type="button" id="saveButton"
                            class="btn btn-success waves-effect btn-label waves-light float-end"><i
                                class="bx bx-plus label-icon"></i> Yeni Üye</a>
                        <button type="button" id="exportExcel"
                            class="btn btn-secondary waves-effect btn-label waves-light float-end me-2"> <i
                                class='bx bxs-file-export label-icon'></i>
                            Excele Aktar
                        </button>
                    </div>

                </div>
<!-- <style>
    .table {
        min-width: 600px;
    }
</style> -->
            
                <div class="card-body overflow-auto">

                    <table id="membersTable" class="datatable table table-hover table-responsive dt-responsive table-bordered nowrap w-100">
                        <thead>
                            <tr>
                                <th class="text-center" >Sıra</th>
                                <th class="text-center" >Üye No</th>
                                <th style="width:150px !important">Ad Soyad</th>
                                <th>Tc Kimlik</th>
                                <th>Unvan</th>
                                <th>İl</th>
                                <th>Kurum</th>
                                <th>Birimi</th>
                                <th>Telefonu</th>
                                <th>Üyelik Tarihi</th>
                                <th>Karar No</th>
                                <th>Durum</th>
                                <th style="width:5%">İşlem</th>
                            </tr>
                        </thead>


                        <tbody>

                            <?php
                            $i = 0;
                            foreach ($uyeler as $uye) {
                                $i++;
                                $enc_id = Security::encrypt($uye->id);
                            ?>
                            <tr>
                                <td class="text-center" >
                                    <?php echo $i ?>
                                </td>
                                <td class="text-center" >
                                    <?php echo $uye->uye_no ?>
                                </td>
                                <td data-tooltip="true" data-tooltip-title="top">
                                    <a href="<?php Route::get("uye/duzenle&id=$enc_id"); ?>"
                                        class="dropdown-item duzenle"><span class="mdi mdi-account font-size-18"></span>
                                        <?php echo $uye->adi_soyadi ?></a>

                                </td>
                               
                                <td class="text-center">
                                    <?php echo $uye->tc_kimlik ?>
                                </td>
                            
                                <td>
                                    <?php echo $uye->unvan ?>
                                </td>
                                <td>
                                    <?php echo $City->getCityName($uye->il) ?>
                                </td>
                                <td>
                                    <?php echo $uye->kurumu ?>
                                </td>
                                <td data-bs-toggle="tooltip" data-bs-original-title="<?php echo $uye->birimi ?>">
                                    <?php echo Helper::short($uye->birimi, 20) ?>
                                </td>
                                <td>
                                    <?php echo $uye->telefon ?>
                                </td>
                                <td>
                                    <?php
                                        //uyeIslemModel'den istifa tarihi boş olan en son kaydı getir
                                        $uye_islem = $UyeIslem->getUyeSonIslem($uye->id);

                                        echo Date::dmY($uye_islem->uyelik_tarihi ?? 0) ?>
                                </td>
                                <td class="text-center">
                                    <?php echo $uye_islem->karar_tarihi_no ?? 0 ?>
                                <td class="text-center">
                                    <?php

                                        //üyelik durumu aktif mi değil mi kontrol et
                                        $durum = $UyeIslem->getUyeDurum($uye->id);
                                        // durum aktif ise success badge, değilse danger badge
                                        if ($durum == 'Aktif') {
                                            echo '<span class="badge bg-success pe-2 ps-2">' . $durum . '</span>';
                                        } else {
                                            echo '<span class="badge bg-danger pe-2 ps-2">' . $durum . '</span>';
                                        } ?>
                                </td>
                                <td class="text-center" style="width:5%">
                                    <div class="flex-shrink-0">
                                        <div class="dropdown align-self-start icon-demo-content">
                                            <a class="dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown"
                                                aria-haspopup="true" aria-expanded="false">
                                                <i class="bx bx-list-ul font-size-24 text-dark"></i>
                                            </a>
                                            <div class="dropdown-menu">
                                                <a href="<?php Route::get("uye/duzenle&id=$enc_id"); ?>"
                                                    class="dropdown-item duzenle"><span
                                                        class="mdi mdi-account-edit font-size-18"></span>
                                                    Düzenle</a>
                                                <a href="#" class="dropdown-item uye-sil"
                                                    data-id="<?php echo $enc_id; ?>"
                                                    data-name="<?php echo $uye->adi_soyadi; ?>">
                                                    <span class="mdi mdi-delete font-size-18"></span>
                                                    Sil</a>
                                                <!-- //Whatsapp grup daveti gönder -->
                                                <?php
                                                    $wa_message = "Merhaba " . $uye->adi_soyadi . ", Sendika üyelerimizin yer aldığı WhatsApp grubumuza katılmak isterseniz aşağıdaki bağlantıdan gruba katılabilirsiniz. https://chat.whatsapp.com/LtglL9ahIBuGR9jvcCNDsj";

                                                    // Mesajı güvenli hale getirmek için urlencode kullanıyoruz
                                                    $wa_message_encoded = urlencode($wa_message);
                                                    $phone_number = urlencode($uye->telefon);
                                                    ?>
                                                <a target="_blank"
                                                    href="https://api.whatsapp.com/send?phone=<?php echo $phone_number; ?>&text=<?php echo $wa_message_encoded; ?>"
                                                    class="dropdown-item">
                                                    <span class="mdi mdi-whatsapp font-size-18"></span> WhatsApp Grup
                                                    Daveti
                                                </a>



                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <?php
                            } ?>
                        </tbody>
                    </table>

                </div>
            </div>
        </div> <!-- end col -->
    </div> <!-- end row -->

</div> <!-- container-fluid -->