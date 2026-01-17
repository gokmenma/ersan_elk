<?php


// Helper sınıflarını dahil et
use App\Helper\Security;
use App\Helper\Date;
use App\Helper\Form;
use App\Helper\Helper;

// KasaModel sınıfını dahil et
use App\Model\KasaModel;

// KasaModel nesnesini oluştur
$Kasa = new KasaModel();

// Oturumdaki kullanıcı ID'sine göre kasa listesini al
$kasalar = $Kasa->getKasaListByOwner($_SESSION['id'] ?? 0);

?>

<!-- Ana içerik kapsayıcısı -->
<div class="container-fluid">
    <!-- Filtre Kartı (şu an için boş) -->


    <!-- Özet Kartları (şu an için boş) -->
    <div class="row g-4">
        <!-- Gelir Kartı (şu an için boş) -->

    </div>
    <!-- İçerik kapsayıcısı -->
    <div class="container-fluid">

        <!-- Sayfa başlığı başlangıcı -->
        <?php
        // Sayfa başlıklarını tanımla
        $maintitle = "Gelir-Gider";
        $title = "Gelir Gider Listesi";
        ?>
        <!-- Breadcrumb bileşenini dahil et -->
        <?php include 'layouts/breadcrumb.php'; ?>
        <!-- Sayfa başlığı sonu -->

        <!-- Ana içerik satırı -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <!-- Kart başlığı ve butonlar -->
                    <div class="card-header d-grid d-md-flex d-block">
                        <div class="card-title col-md-8">
                            <!-- Sayfa başlığı ve açıklaması -->
                            <h4 class="card-title">Gelir-Gider Listesi</h4>
                            <p class="card-title-desc">Gelir Gider işlemlerini görüntüleyebilir ve yeni işlem
                                ekleyebilirsiniz.

                            </p>
                        </div>

                        <!-- İşlem butonları -->
                        <div class="col-md-4">
                            <!-- Yeni İşlem Ekle butonu -->
                            <a type="button" id="gelirGiderEkle" href="index?p=kasa/duzenle"
                                class="btn btn-primary waves-effect btn-label waves-light float-end new"
                                ><i
                                    class="bx bx-save label-icon"></i>Yeni Kasa
                            </a>
                            <!-- Excele Aktar butonu -->
                            <button type="button" id="exportExcel"
                                class="btn btn-secondary waves-effect btn-label waves-light float-end me-2"> <i
                                    class='bx bxs-file-export label-icon'></i>
                                Excele Aktar
                            </button>
                        </div>

                    </div>

                    <!-- Kart içeriği: Kasa listesi tablosu -->
                    <div class="card-body overflow-auto">
                        <table id="gelirGiderTable" class="datatable table-hover table table-bordered nowrap w-100">
                            <!-- Tablo başlığı -->
                            <thead>
                                <tr>
                                    <th class="text-center" style="width:7%">Sıra</th>
                                    <th class="text-center">Hesap No</th>
                                    <th class="text-center ">Kasa Adı</th>
                                    <th>Kasa Tipi</th>
                                    <th class="text-center no-export">Para Birimi</th>
                                    <th>Varsayılan mı?</th>
                                    <th class="text-center no-export">Açıklama</th>
                                    <th class="text-center no-export">Mevcut Bakiye</th>
                                    <th class="text-center no-export">Aktif</th>
                                    <th style="width:5%">İşlem</th>
                                </tr>
                            </thead>

                            <!-- Tablo içeriği -->
                            <tbody>
                                <?php
                                // Her bir kasa için döngü
                                foreach ($kasalar as $kasa) {
                                    // Kasa ID'sini şifrele
                                    $enc_id = Security::encrypt($kasa->id);
                                ?>
                                <!-- Her bir kasa için tablo satırı -->
                                <tr id="gelir_gider_<?php echo $kasa->id ?>" data-id="<?php echo $enc_id ?>">
                                    <!-- Kasa bilgileri -->
                                    <td class="text-center">
                                        <?php echo $kasa->id; ?>
                                    </td>

                                    <td class="text-center cursor-pointer" data-bs-toggle="tooltip" data-bs-placement="top" title="Kasa Hareketlerini ve detayları görmek için tıklayınız">
                                        <?php echo $kasa->hesap_no; ?>

                                    </td>

                                    <td class="text-center">
                                        <?php echo $kasa->kasa_adi; ?>
                                    </td>
                                    <td>
                                        <?php echo $kasa->kasa_tipi; ?>
                                    </td>
                                    <td class="text-center no-export">
                                        <?php echo $kasa->para_birimi; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php echo $kasa->varsayilan_mi ? '<span class="badge bg-success">Evet</span>' : '<span class="badge bg-secondary">Hayır</span>'; ?>
                                    </td>
                                    <td class="text-center no-export">
                                        <?php echo $kasa->aciklama; ?>
                                    </td>
                                    <td class="text-center no-export">
                                        <?php echo Helper::formattedMoney($kasa->baslangic_bakiyesi); ?>
                                    </td>
                                    <td class="text-center no-export">
                                        <?php echo $kasa->aktif ? '<span class="badge bg-success">Aktif</span>' : '<span class="badge bg-danger">Pasif</span>'; ?>
                                    </td>

                                    <!-- İşlem butonları -->
                                    <td class="text-center no-export" style="width:5%">
                                        <div class="flex-shrink-0">
                                            <div class="dropdown align-self-start icon-demo-content">
                                                <a class="dropdown-toggle" href="#" role="button"
                                                    data-bs-toggle="dropdown" aria-haspopup="true"
                                                    aria-expanded="false">
                                                    <i class="bx bx-list-ul font-size-24 text-dark"></i>
                                                </a>
                                                <div class="dropdown-menu">
                                                    <!-- Düzenle butonu -->
                                                    <a href="index?p=kasa/duzenle&id=<?php echo $enc_id; ?>" data-id=<?php echo $enc_id; ?>
                                                        class="dropdown-item duzenle"><span
                                                            class="mdi mdi-account-edit font-size-18"></span>
                                                        Düzenle</a>
                                                    <!-- Sil butonu -->
                                                    <a href="javascript:void(0);" class="dropdown-item kasa-sil"
                                                        data-id="<?php echo $enc_id; ?>" data-name="">
                                                        <span class="mdi mdi-delete font-size-18"></span>
                                                        Sil</a>
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