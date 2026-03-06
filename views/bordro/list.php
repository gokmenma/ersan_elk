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

/**Eğer seçil dönem veritabanında yoksa seçili dönem id session'a ata */
$seciliDonemKontrol = $BordroDonem->find($selectedDonemId);
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
    $selectedDonem = $BordroDonem->getDonemById($selectedDonemId);
    if ($selectedDonem) {
        $_sqlStart = microtime(true);
        $personeller = $BordroPersonel->getPersonellerByDonem($selectedDonemId);
        $_sqlTime = round((microtime(true) - $_sqlStart) * 1000);
    }
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

        .dropdown-menu .show {
            z-index: 1060;
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
                            <?php if ($selectedDonem): ?>
                                <div class="vr mx-1" style="height: 25px; align-self: center;"></div>
                                <div class="form-check form-switch px-4">
                                    <input class="form-check-input" type="checkbox" role="switch" id="switchDonemDurum"
                                        <?= $donemKapali ? 'checked' : '' ?>>
                                    <label
                                        class="form-check-label small <?= $donemKapali ? 'text-danger' : 'text-success' ?> fw-bold"
                                        for="switchDonemDurum" style="font-size: 11px;">
                                        <?= $donemKapali ? 'KAPALI' : 'AÇIK' ?>
                                    </label>
                                </div>
                                <div class="vr mx-1" style="height: 25px; align-self: center;"></div>
                                <div class="form-check form-switch px-4">
                                    <input class="form-check-input" type="checkbox" role="switch" id="switchPersonelGorsun"
                                        <?= ($selectedDonem->personel_gorsun == 1) ? 'checked' : '' ?>>
                                    <label
                                        class="form-check-label small <?= ($selectedDonem->personel_gorsun == 1) ? 'text-success' : 'text-danger' ?> fw-bold"
                                        for="switchPersonelGorsun" style="font-size: 11px;">
                                        PERSONEL GÖRSÜN
                                    </label>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="d-flex align-items-center bg-white border rounded shadow-sm p-1 gap-1">
                            <button type="button" class="btn btn-link btn-sm text-success text-decoration-none px-2"
                                data-bs-toggle="modal" data-bs-target="#yeniDonemModal" title="Yeni Dönem">
                                <i class="mdi mdi-plus-circle fs-5"></i>
                            </button>
                            <?php if ($selectedDonem && !$donemKapali): ?>
                                <!-- <div class="vr mx-1" style="height: 20px; align-self: center;"></div>
                                <button type="button" class="btn btn-link btn-sm text-primary text-decoration-none px-2"
                                    id="btnHeaderEditDonem" title="Düzenle">
                                    <i class="mdi mdi-pencil fs-5"></i>
                                </button> -->
                            <?php endif; ?>
                            <?php if (!$donemKapali) { ?>
                                <div class="vr mx-1" style="height: 20px; align-self: center;"></div>
                                <button type="button" id="donemSil"
                                    class="btn btn-link btn-sm text-danger text-decoration-none px-2" title="Dönemi Sil">
                                    <i class="mdi mdi-trash-can fs-5"></i>
                                </button>
                            <?php } ?>
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
                                                href="javascript:void(0);" data-bs-toggle="modal"
                                                data-bs-target="#odemeEkleModal">
                                                <i class="mdi mdi-wallet me-2 text-info fs-5"></i> Ödeme Dağıt (Excel)
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
                        <?php
                        // ========== TEK DÖNGÜDE ÖN-HESAPLAMA (Performance Optimization) ==========
                        // Tüm personel değerlerini tek döngüde hesapla, sonuçları $preCalc dizisine kaydet
                        // Hem özet kartlarda hem tablo satırlarında bu veriler kullanılacak
                        $toplamAlacagi = 0;
                        $toplamKesintiHaricIcra = 0;
                        $toplamNetAlacagi = 0;
                        $toplamIcra = 0;
                        $toplamBanka = 0;
                        $toplamSodexo = 0;
                        $toplamElden = 0;
                        $latestCalculation = null;
                        $preCalc = []; // Hesaplanmış değerleri sakla
                    
                        // Dönem tarihlerini döngü dışında bir kez hesapla
                        $donemBasTs = $selectedDonem ? strtotime($selectedDonem->baslangic_tarihi) : 0;
                        $donemBitTs = $selectedDonem ? strtotime($selectedDonem->bitis_tarihi) : 0;
                        $aydakiGunSayisi = $selectedDonem ? date('t', $donemBasTs) : 30;

                        // Asgari ücreti çek
                        $asgariUcretNet = 0;
                        if ($selectedDonem) {
                            $asgariUcretNet = $BordroParametre->getGenelAyar('asgari_ucret_net', $selectedDonem->baslangic_tarihi) ?? 17002.12;
                        }

                        $gorevGecmisiEksikPersoneller = []; // Görev geçmişi eksik personeller
                    
                        foreach ($personeller as $p) {
                            $rawEkOdeme = floatval($p->guncel_toplam_ek_odeme);

                            // Maaş tutarı ve durumunu belirle
                            if (!empty($p->gorev_gecmisi_var)) {
                                $pMaasDurumu = $p->gg_maas_durumu ?? '';
                                $fallbackMaasTutari = floatval($p->gg_maas_tutari ?? 0);
                            } else {
                                $pMaasDurumu = $p->maas_durumu ?? '';
                                $fallbackMaasTutari = floatval($p->maas_tutari ?? 0);
                                // Görev geçmişi eksik personeli kaydet
                                $gorevGecmisiEksikPersoneller[] = $p->adi_soyadi;
                            }

                            // Eğer daha önce hesaplama yapılmışsa ve ağırlıklı nominal maaş varsa onu kullan (Pro-rata gösterimi için)
                            if ($p->hd_nominal_maas !== null && floatval($p->hd_nominal_maas) > 0) {
                                $pMaasTutari = floatval($p->hd_nominal_maas);
                            } else {
                                $pMaasTutari = $fallbackMaasTutari;
                            }

                            $pNetMaas = floatval($p->net_maas ?? 0);
                            $pToplamKesinti = floatval($p->kesinti_tutar ?? 0);
                            $pIsNet = $pMaasDurumu == 'Net';
                            $pIsPrimUsulu = (stripos($pMaasDurumu, 'Prim') !== false);

                            // SQL'den JSON_EXTRACT ile çekilen değerleri direkt kullan (json_decode yok)
                            $pIcra = floatval($p->hd_icra_kesintisi ?? 0);
                            $pKesintiHaricIcra = $pToplamKesinti - $pIcra;

                            // Çalışma günü hesaplama
                            // 1) İşe giriş dönem içindeyse: ayın_gün_sayısı - giriş_günü + 1
                            // 2) Tam ay + izin yok: 30 (ticari)
                            // 3) Tam ay + izin var: ayın_gün_sayısı - izin_günü
                            $pGunlukBase = 30;
                            $pUcretsizIzinGunu = 0;
                            $pUcretliIzinGunu = 0;
                            $pIseGirisDI = false;
                            $pIstenCikisDI = false;

                            // JSON_EXTRACT ile çekilen izin değerlerini önce al
                            if ($p->hd_ucretsiz_izin_gunu !== null) {
                                $pUcretsizIzinGunu = intval($p->hd_ucretsiz_izin_gunu);
                            } elseif ($p->hd_ucretsiz_izin_dusumu !== null && $p->hd_nominal_maas !== null && floatval($p->hd_nominal_maas) > 0) {
                                $pUcretsizIzinGunu = round(floatval($p->hd_ucretsiz_izin_dusumu) / (floatval($p->hd_nominal_maas) / 30));
                            }
                            if ($p->hd_ucretli_izin_gunu !== null) {
                                $pUcretliIzinGunu = intval($p->hd_ucretli_izin_gunu);
                            }

                            if ($selectedDonem) {
                                if (!empty($p->ise_giris_tarihi)) {
                                    $iseGirisTs = strtotime($p->ise_giris_tarihi);
                                    if ($iseGirisTs > $donemBasTs) {
                                        $pIseGirisDI = true;
                                    }
                                }
                                if (!empty($p->isten_cikis_tarihi)) {
                                    $istenCikisTs = strtotime($p->isten_cikis_tarihi);
                                    if ($istenCikisTs >= $donemBasTs && $istenCikisTs < $donemBitTs) {
                                        $pIstenCikisDI = true;
                                    }
                                }
                            }

                            if ($pIseGirisDI && $pIstenCikisDI) {
                                $pGunlukBase = date('j', $istenCikisTs) - date('j', $iseGirisTs) + 1;
                            } elseif ($pIseGirisDI) {
                                $pGunlukBase = $aydakiGunSayisi - date('j', $iseGirisTs) + 1;
                            } elseif ($pIstenCikisDI) {
                                $pGunlukBase = date('j', $istenCikisTs);
                            } elseif ($pUcretsizIzinGunu > 0 || $pUcretliIzinGunu > 0) {
                                $pGunlukBase = $aydakiGunSayisi;
                            } else {
                                $pGunlukBase = 30;
                            }
                            if ($pGunlukBase < 0)
                                $pGunlukBase = 0;

                            // USER REQ: Maaş hesaplaması görev geçmişi kapsamına göre olmalı (Örn: Geçmiş 1 günlük ise 1 gün ödenmeli)
                            if (!empty($p->gorev_gecmisi_var) && isset($p->gg_toplam_gun)) {
                                $pGunlukBase = min($pGunlukBase, intval($p->gg_toplam_gun));
                            }

                            $pCalismaGunu = $pGunlukBase;
                            if ($p->hd_fiili_calisma_gunu !== null) {
                                $pCalismaGunu = intval($p->hd_fiili_calisma_gunu);
                            } elseif ($p->hd_fiili_calisma_gunu === null) {
                                $pCalismaGunu = $pGunlukBase - $pUcretsizIzinGunu - $pUcretliIzinGunu;
                            }

                            // Toplam Alacağı
                            if ($pIsPrimUsulu) {
                                $pToplamAlacagi = floatval($p->brut_maas ?? 0) + $rawEkOdeme;
                            } elseif ($pIsNet || $pMaasDurumu == 'Brüt') {
                                $pToplamAlacagi = (($pMaasTutari / 30) * $pCalismaGunu) + $rawEkOdeme;
                            } else {
                                $pToplamAlacagi = $pMaasTutari + $rawEkOdeme;
                            }

                            // Net alacağı = Toplam Alacağı - Kesinti (hariç icra)
                            // NOT: $pNetMaas (DB net_maas) icra düşülmüş hali, bu yüzden doğrudan kullanılmamalı
                            $pNetAlacagi = $pToplamAlacagi - $pKesintiHaricIcra;

                            // Elden ödeme (DB'den varsa al, yoksa hesapla)
                            $eldenP = floatval($p->elden_odeme ?? 0);

                            // Net Maaş (icra düştükten sonra dağıtılacak tutar)
                            $pNetMaasGercek = $pNetAlacagi - $pIcra;
                            if ($pNetMaasGercek < 0)
                                $pNetMaasGercek = 0;

                            // Banka = (Asgari / 30 * Gün) - İcra
                            // Formül: geçerli dönemdeki asgari ücret / 30 * çalışma günü - icra kesintisi
                    
                            $sodexoP = floatval($p->sodexo_odemesi ?? 0);

                            if ($pCalismaGunu >= 30) {
                                $bankaBaz = $asgariUcretNet;
                            } else {
                                $bankaBaz = ($asgariUcretNet / 30) * $pCalismaGunu;
                            }

                            // Maksimum kontrolü (Net - Sodexo) - Banka ödemesi toplam alacağı geçemez
                            $bankaMax = max(0, $pNetAlacagi - $sodexoP);
                            $bankaBaz = min($bankaBaz, $bankaMax);

                            $bankaP = max(0, $bankaBaz - $pIcra);
                            if ($bankaP < 0)
                                $bankaP = 0;

                            if (($p->sgk_yapilan_firma ?? '') === 'İŞKUR') {
                                $bankaP = 0;
                            }

                            $digerP = floatval($p->diger_odeme ?? 0);

                            // Elden = Net - Banka - Sodexo - Diğer
                            // $pNetMaasGercek (İcra düşülmüş net maaş)
                            // $eldenP = floatval($p->elden_odeme ?? 0); // DB'den gelen değeri eziyoruz
                            $eldenP = max(0, $pNetMaasGercek - $bankaP - $sodexoP - $digerP);

                            // Toplamları güncelle
                            $toplamAlacagi += $pToplamAlacagi;
                            $toplamKesintiHaricIcra += $pKesintiHaricIcra;
                            $toplamNetAlacagi += $pNetAlacagi;
                            $toplamIcra += $pIcra;
                            $toplamBanka += $bankaP;
                            $toplamSodexo += $sodexoP;
                            $toplamElden += $eldenP;

                            // En son hesaplama tarihi
                            if ($p->hesaplama_tarihi && (!$latestCalculation || $p->hesaplama_tarihi > $latestCalculation)) {
                                $latestCalculation = $p->hesaplama_tarihi;
                            }

                            // Ön-hesaplama sonuçlarını kaydet (tablo satırında kullanılacak)
                            $preCalc[$p->id] = [
                                'enc_id' => Security::encrypt($p->personel_id),
                                'toplamAlacagi' => $pToplamAlacagi,
                                'kesintiHaricIcra' => $pKesintiHaricIcra,
                                'netAlacagi' => $pNetAlacagi,
                                'icraKesintisi' => $pIcra,
                                'calismaGunu' => $pCalismaGunu,
                                'eldenOdeme' => $eldenP,
                                'bankaOdemesi' => $bankaP,
                                'sodexoOdemesi' => $sodexoP,
                            ];
                        }
                        ?>


                        <!-- Üst Bilgi Çubuğu (Dashboard Stili) -->
                        <div class="card border-0 shadow-sm mb-4 bordro-info-bar"
                            style="border-radius: 20px; background: rgba(231, 111, 81, 0.03); border: 1px solid rgba(231, 111, 81, 0.1) !important;">
                            <div class="card-body p-3 d-flex align-items-center justify-content-between">
                                <div class="d-flex align-items-center">
                                    <div class="bg-white rounded-3 shadow-sm p-2 me-3 d-flex align-items-center justify-content-center"
                                        style="width: 45px; height: 45px;">
                                        <i class="bx bx-calendar-event fs-3" style="color: #E76F51;"></i>
                                    </div>
                                    <div>
                                        <div class="d-flex align-items-center">
                                            <h5 class="mb-0 fw-bold bordro-text-heading" id="displayDonemAdi">
                                                <?= htmlspecialchars($selectedDonem->donem_adi) ?>
                                            </h5>
                                            <?php if (!$donemKapali): ?>
                                                <button type="button" class="btn btn-sm btn-link p-0 ms-2 text-muted"
                                                    id="btnEditDonemAdi" title="Dönem Adını Güncelle">
                                                    <i class="bx bx-edit-alt fs-6"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                        <small class="text-muted fw-medium">
                                            <i class="bx bx-time-five me-1"></i>
                                            <?= date('d.m.Y', strtotime($selectedDonem->baslangic_tarihi)) ?> -
                                            <?= date('d.m.Y', strtotime($selectedDonem->bitis_tarihi)) ?>
                                        </small>
                                    </div>
                                </div>
                                <div class="d-none d-md-flex align-items-center gap-3">
                                    <div class="text-end me-2">
                                        <p class="text-muted mb-0 small fw-bold">TOPLAM PERSONEL</p>
                                        <h5 class="mb-0 fw-bold bordro-text-heading"><?= count($personeller) ?> <span
                                                class="small text-muted fw-normal">Kişi</span></h5>
                                    </div>
                                    <div class="vr text-muted opacity-25" style="height: 35px;"></div>
                                    <div class="d-flex align-items-start gap-2">
                                        <span
                                            class="badge shadow-sm border rounded-pill px-3 py-2 fw-bold d-flex align-items-center"
                                            style="background: var(--bs-card-bg); color: var(--bs-body-color) !important;">
                                            <span class="rounded-circle me-2"
                                                style="width: 8px; height: 8px; background: <?= $donemKapali ? '#f43f5e' : '#10b981' ?>;"></span>
                                            <?= $donemKapali ? 'KAPALI' : 'AÇIK' ?>
                                        </span>
                                        <?php if ($latestCalculation): ?>
                                            <div class="d-flex flex-column align-items-center">
                                                <span
                                                    class="badge shadow-sm border rounded-pill px-3 py-2 fw-bold d-flex align-items-center"
                                                    style="background: var(--bs-card-bg); color: var(--bs-body-color) !important;">
                                                    <span class="rounded-circle me-2"
                                                        style="width: 8px; height: 8px; background: #10b981;"></span>
                                                    HESAPLANDI
                                                </span>
                                                <div class="text-muted mt-1"
                                                    style="font-size: 9px; font-weight: 600; opacity: 0.8;">
                                                    <i
                                                        class="bx bx-check-double me-1"></i><?= date('d.m.Y H:i', strtotime($latestCalculation)) ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($gorevGecmisiEksikPersoneller)): ?>
                                            <span
                                                class="badge shadow-sm border rounded-pill px-3 py-2 fw-bold d-flex align-items-center"
                                                style="background: rgba(245, 158, 11, 0.1); color: #f59e0b !important; cursor: help;"
                                                data-bs-toggle="tooltip" data-bs-html="true"
                                                title="<?= htmlspecialchars(implode(', ', $gorevGecmisiEksikPersoneller)) ?>">
                                                <i class="bx bx-error-circle me-1"></i>
                                                <?= count($gorevGecmisiEksikPersoneller) ?> Görev Geçmişi Eksik
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Dönem Toplamları Kartları (Dashboard Stili) -->
                        <div class="row g-3 mb-4">
                            <!-- Toplam Alacağı -->
                            <div class="col-xl col-md-4">
                                <div class="card border-0 shadow-sm h-100 bordro-summary-card"
                                    style="--card-color: #E76F51; border-bottom: 3px solid var(--card-color) !important;">
                                    <div class="card-body p-3">
                                        <div class="icon-label-container">
                                            <div class="icon-box" style="background: rgba(231, 111, 81, 0.1);">
                                                <i class="bx bx-receipt fs-4" style="color: #E76F51;"></i>
                                            </div>
                                            <span class="text-muted small fw-bold"
                                                style="font-size: 0.65rem;">HAKEDİŞ</span>
                                        </div>
                                        <p class="text-muted mb-1 small fw-bold"
                                            style="letter-spacing: 0.5px; opacity: 0.7;">TOPLAM ALACAĞI</p>
                                        <h4 class="mb-0 fw-bold bordro-text-heading">
                                            <?= number_format($toplamAlacagi, 2, ',', '.') ?> <span
                                                style="font-size: 0.85rem; font-weight: 600;">₺</span>
                                        </h4>
                                    </div>
                                </div>
                            </div>

                            <!-- Kesinti Tutarı -->
                            <div class="col-xl col-md-4">
                                <div class="card border-0 shadow-sm h-100 bordro-summary-card"
                                    style="--card-color: #f43f5e; border-bottom: 3px solid var(--card-color) !important;">
                                    <div class="card-body p-3">
                                        <div class="icon-label-container">
                                            <div class="icon-box" style="background: rgba(244, 63, 94, 0.1);">
                                                <i class="bx bx-minus-circle fs-4 text-danger"></i>
                                            </div>
                                            <span class="text-muted small fw-bold"
                                                style="font-size: 0.65rem;">KESİNTİ</span>
                                        </div>
                                        <p class="text-muted mb-1 small fw-bold"
                                            style="letter-spacing: 0.5px; opacity: 0.7;">KESİNTİ TUTARI (HARİÇ İCRA)</p>
                                        <h4 class="mb-0 fw-bold bordro-text-heading">
                                            <?= number_format($toplamKesintiHaricIcra, 2, ',', '.') ?> <span
                                                style="font-size: 0.85rem; font-weight: 600;">₺</span>
                                        </h4>
                                    </div>
                                </div>
                            </div>

                            <!-- Net Alacağı -->
                            <div class="col-xl col-md-4">
                                <div class="card border-0 shadow-sm h-100 bordro-summary-card"
                                    style="--card-color: #2a9d8f; border-bottom: 3px solid var(--card-color) !important;">
                                    <div class="card-body p-3">
                                        <div class="icon-label-container">
                                            <div class="icon-box" style="background: rgba(42, 157, 143, 0.1);">
                                                <i class="bx bx-wallet fs-4" style="color: #2a9d8f;"></i>
                                            </div>
                                            <span class="text-muted small fw-bold" style="font-size: 0.65rem;">NET</span>
                                        </div>
                                        <p class="text-muted mb-1 small fw-bold"
                                            style="letter-spacing: 0.5px; opacity: 0.7;">NET ALACAĞI</p>
                                        <h4 class="mb-0 fw-bold bordro-text-heading">
                                            <?= number_format($toplamNetAlacagi, 2, ',', '.') ?> <span
                                                style="font-size: 0.85rem; font-weight: 600;">₺</span>
                                        </h4>
                                    </div>
                                </div>
                            </div>

                            <!-- İcra Kesintisi -->
                            <div class="col-xl col-md-4">
                                <div class="card border-0 shadow-sm h-100 bordro-summary-card"
                                    style="--card-color: #ef4444; border-bottom: 3px solid var(--card-color) !important;">
                                    <div class="card-body p-3">
                                        <div class="icon-label-container">
                                            <div class="icon-box" style="background: rgba(239, 68, 68, 0.1);">
                                                <i class="bx bx-shield-x fs-4 text-danger"></i>
                                            </div>
                                            <span class="text-muted small fw-bold" style="font-size: 0.65rem;">İCRA</span>
                                        </div>
                                        <p class="text-muted mb-1 small fw-bold"
                                            style="letter-spacing: 0.5px; opacity: 0.7;">İCRA KESİNTİSİ</p>
                                        <h4 class="mb-0 fw-bold bordro-text-heading">
                                            <?= number_format($toplamIcra, 2, ',', '.') ?> <span
                                                style="font-size: 0.85rem; font-weight: 600;">₺</span>
                                        </h4>
                                    </div>
                                </div>
                            </div>

                            <!-- Banka -->
                            <div class="col-xl col-md-4">
                                <div class="card border-0 shadow-sm h-100 bordro-summary-card"
                                    style="--card-color: #0ea5e9; border-bottom: 3px solid var(--card-color) !important;">
                                    <div class="card-body p-3">
                                        <div class="icon-label-container">
                                            <div class="icon-box" style="background: rgba(14, 165, 233, 0.1);">
                                                <i class="bx bxs-bank fs-4 text-info"></i>
                                            </div>
                                            <span class="text-muted small fw-bold" style="font-size: 0.65rem;">RESMİ</span>
                                        </div>
                                        <p class="text-muted mb-1 small fw-bold"
                                            style="letter-spacing: 0.5px; opacity: 0.7;">BANKA ÖDEMESİ</p>
                                        <h4 class="mb-0 fw-bold bordro-text-heading">
                                            <?= number_format($toplamBanka, 2, ',', '.') ?> <span
                                                style="font-size: 0.85rem; font-weight: 600;">₺</span>
                                        </h4>
                                    </div>
                                </div>
                            </div>

                            <!-- Sodexo -->
                            <div class="col-xl col-md-4">
                                <div class="card border-0 shadow-sm h-100 bordro-summary-card"
                                    style="--card-color: #8b5cf6; border-bottom: 3px solid var(--card-color) !important;">
                                    <div class="card-body p-3">
                                        <div class="icon-label-container">
                                            <div class="icon-box" style="background: rgba(139, 92, 246, 0.1);">
                                                <i class="bx bx-food-menu fs-4" style="color: #8b5cf6;"></i>
                                            </div>
                                            <span class="text-muted small fw-bold" style="font-size: 0.65rem;">YEMEK</span>
                                        </div>
                                        <p class="text-muted mb-1 small fw-bold"
                                            style="letter-spacing: 0.5px; opacity: 0.7;">SODEXO</p>
                                        <h4 class="mb-0 fw-bold bordro-text-heading">
                                            <span id="total-sodexo"><?= number_format($toplamSodexo, 2, ',', '.') ?></span>
                                            <span style="font-size: 0.85rem; font-weight: 600;">₺</span>
                                        </h4>
                                    </div>
                                </div>
                            </div>

                            <!-- Elden -->
                            <div class="col-xl col-md-4">
                                <div class="card border-0 shadow-sm h-100 bordro-summary-card"
                                    style="--card-color: #f59e0b; border-bottom: 3px solid var(--card-color) !important;">
                                    <div class="card-body p-3">
                                        <div class="icon-label-container">
                                            <div class="icon-box" style="background: rgba(245, 158, 11, 0.1);">
                                                <i class="bx bx-wallet-alt fs-4 text-warning"></i>
                                            </div>
                                            <span class="text-muted small fw-bold" style="font-size: 0.65rem;">NAKİT</span>
                                        </div>
                                        <p class="text-muted mb-1 small fw-bold"
                                            style="letter-spacing: 0.5px; opacity: 0.7;">ELDEN ÖDEME</p>
                                        <h4 class="mb-0 fw-bold bordro-text-heading">
                                            <span id="total-elden"><?= number_format($toplamElden, 2, ',', '.') ?></span>
                                            <span style="font-size: 0.85rem; font-weight: 600;">₺</span>
                                        </h4>
                                    </div>
                                </div>
                            </div>
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
                                            <th style="width: 20px;">
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
                                            <th class="text-end" data-filter="number">Net Alacağı</th>
                                            <th class="text-end" data-filter="number">İcra Kesintisi</th>
                                            <th class="text-end" data-filter="number">Banka</th>
                                            <th class="text-end" data-filter="number">Sodexo</th>
                                            <th class="text-end" data-filter="number">Elden</th>
                                            <th class="text-center">İşlemler</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($personeller)): ?>
                                            <tr>
                                                <td colspan="12" class="text-center text-muted py-4">
                                                    <i class="bx bx-user-x fs-1 d-block mb-2"></i>
                                                    Bu döneme henüz personel eklenmemiş.<br>
                                                    <small>"Personelleri Güncelle" butonuna tıklayarak personelleri
                                                        ekleyebilirsiniz.</small>
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php $idx = 1;
                                            foreach ($personeller as $personel):
                                                // Ön-hesaplanmış değerleri oku (tekrar hesaplama yok)
                                                $pc = $preCalc[$personel->id];
                                                $enc_id = $pc['enc_id'];
                                                $toplamAlacagiPersonel = $pc['toplamAlacagi'];
                                                $kesintiHaricIcra = $pc['kesintiHaricIcra'];
                                                $netAlacagi = $pc['netAlacagi'];
                                                $icraKesintisi = $pc['icraKesintisi'];
                                                $calismaGunu = $pc['calismaGunu'];
                                                $eldenOdeme = $pc['eldenOdeme'];
                                                $bankaOdemesi = $pc['bankaOdemesi'];
                                                $sodexoOdemesi = $pc['sodexoOdemesi'];
                                                ?>
                                                <tr data-id="<?= $personel->id ?>">
                                                    <td>
                                                        <div class="form-check">
                                                            <input type="checkbox" class="form-check-input personel-check"
                                                                value="<?= $personel->id ?>">
                                                        </div>
                                                    </td>
                                                    <td class="text-center fw-bold text-muted"><?= $idx++ ?></td>
                                                    <td class="text-center">
                                                        <?php
                                                        $deptName = (!empty($personel->gorev_gecmisi_var) && !empty($personel->gg_departman)) ? $personel->gg_departman : ($personel->departman ?? '-');
                                                        $deptUp = mb_convert_case($deptName, MB_CASE_UPPER, "UTF-8");
                                                        $dInfo = ['code' => '??', 'color' => '#6c757d'];

                                                        if (strpos($deptUp, 'OKUMA') !== false)
                                                            $dInfo = ['code' => 'EO', 'color' => '#0ea5e9'];
                                                        elseif (strpos($deptUp, 'KESME') !== false)
                                                            $dInfo = ['code' => 'KA', 'color' => '#f43f5e'];
                                                        elseif (strpos($deptUp, 'SAYAÇ') !== false || strpos($deptUp, 'DEGİŞ') !== false)
                                                            $dInfo = ['code' => 'ST', 'color' => '#10b981'];
                                                        elseif (strpos($deptUp, 'KAÇAK') !== false)
                                                            $dInfo = ['code' => 'KÇ', 'color' => '#8b5cf6'];
                                                        else {
                                                            $words = explode(' ', $deptUp);
                                                            if (count($words) >= 2) {
                                                                $dInfo['code'] = mb_substr($words[0], 0, 1) . mb_substr($words[1], 0, 1);
                                                            } else {
                                                                $dInfo['code'] = mb_substr($deptUp, 0, 2);
                                                            }
                                                        }
                                                        ?>
                                                        <div class="dept-badge" style="--dept-color: <?= $dInfo['color'] ?>;"
                                                            data-bs-toggle="tooltip" title="<?= htmlspecialchars($deptName) ?>">
                                                            <?= $dInfo['code'] ?>
                                                        </div>
                                                        <span class="d-none"><?= $dInfo['code'] ?>
                                                            <?= htmlspecialchars($deptName) ?></span>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        if (!empty($personel->ekip_adi) && $personel->ekip_adi !== "YOK") {


                                                            $ekipler = explode(',', $personel->ekip_adi);
                                                            echo '<div class="d-flex flex-wrap">';
                                                            foreach ($ekipler as $ekip) {
                                                                $cleanEkip = trim($ekip);
                                                                $cleanEkip = preg_replace('/ER-SAN ELEKTRİK|ERSAN ELEKTRİK|ER SAN ELEKTRİK/i', '', $cleanEkip);
                                                                $cleanEkip = trim($cleanEkip);

                                                                if (empty($cleanEkip))
                                                                    continue;

                                                                // Departmana göre renk belirle
                                                                $colorClass = "bg-secondary-subtle text-secondary border-secondary-subtle";
                                                                if (strpos($deptUp, 'OKUMA') !== false) {
                                                                    $colorClass = "bg-primary-subtle text-primary border-primary-subtle";
                                                                } elseif (strpos($deptUp, 'KESME') !== false) {
                                                                    $colorClass = "bg-danger-subtle text-danger border-danger-subtle";
                                                                } elseif (strpos($deptUp, 'SAYAÇ') !== false || strpos($deptUp, 'DEGİŞ') !== false) {
                                                                    $colorClass = "bg-success-subtle text-success border-success-subtle";
                                                                } elseif (strpos($deptUp, 'KAÇAK') !== false) {
                                                                    $colorClass = "bg-info-subtle text-info border-info-subtle";
                                                                }

                                                                echo '<span class="badge ' . $colorClass . ' font-size-12 px-2 py-1 mb-1 me-1 border">' . htmlspecialchars($cleanEkip) . '</span>';
                                                            }
                                                            echo '</div>';

                                                            if (!empty($personel->ekip_bolge) && $personel->ekip_bolge !== "---") {
                                                                echo '<div class="text-muted small mt-1"><i class="bx bx-map-pin"></i> ' . htmlspecialchars($personel->ekip_bolge) . '</div>';
                                                            }
                                                        }
                                                        ?>
                                                    </td>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <img src="<?= !empty($personel->resim_yolu) ? $personel->resim_yolu : 'assets/images/users/user-dummy-img.jpg' ?>"
                                                                alt="" class="rounded-circle avatar-sm me-2">
                                                            <div>
                                                                <div class="fw-medium">
                                                                    <a target="_blank"
                                                                        href="index?p=personel/manage&id=<?= $enc_id ?>"><?= htmlspecialchars($personel->adi_soyadi) ?></a>
                                                                </div>
                                                                <small class="text-muted"
                                                                    style="font-size: 10px; letter-spacing: 0.5px;">TC:
                                                                    <?= htmlspecialchars($personel->tc_kimlik_no ?? '-') ?></small>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td class="text-center" style="font-size: 12px;">
                                                        <?php if (empty($personel->gorev_gecmisi_var)): ?>
                                                            <span
                                                                class="badge bg-warning-subtle text-warning border border-warning fw-medium px-2 py-1"
                                                                data-bs-toggle="tooltip"
                                                                title="Görev geçmişi tanımlı değil! Personel tablosundaki veri kullanılıyor.">
                                                                <i
                                                                    class="bx bx-error-circle me-1"></i><?= htmlspecialchars($personel->maas_durumu ?? '-') ?>
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="badge bg-light text-dark border fw-medium px-2 py-1">
                                                                <?= htmlspecialchars($personel->gg_maas_durumu ?? $personel->maas_durumu ?? '-') ?>
                                                            </span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="text-center fw-bold">
                                                        <a href="index?p=personel/manage&id=<?= $enc_id ?>&tab=izinler&view=calendar"
                                                            target="_blank" class="text-primary text-decoration-none"
                                                            title="İzin/Rapor Takvimini Görüntüle">
                                                            <?= $calismaGunu ?>
                                                        </a>
                                                    </td>

                                                    <td class="text-end text-dark fw-bold">
                                                        <span class="cursor-pointer btn-detail text-primary"
                                                            data-id="<?= $personel->id ?>" title="Bordro Detayını Gör">
                                                            <?= number_format($toplamAlacagiPersonel, 2, ',', '.') ?> ₺
                                                        </span>
                                                    </td>
                                                    <td class="text-end text-danger fw-bold">
                                                        <span class="cursor-pointer btn-kesinti-ekle text-danger"
                                                            data-id="<?= $personel->personel_id ?>"
                                                            data-ad="<?= htmlspecialchars($personel->adi_soyadi) ?>"
                                                            data-maas="<?= floatval($personel->maas_tutari ?? 0) ?>"
                                                            data-maas-durumu="<?= $personel->maas_durumu ?? '' ?>">
                                                            <?= number_format($kesintiHaricIcra, 2, ',', '.') ?> ₺
                                                        </span>
                                                    </td>
                                                    <td class="text-end text-success fw-bold">
                                                        <span class="cursor-pointer btn-detail text-success"
                                                            data-id="<?= $personel->id ?>">
                                                            <?= number_format($netAlacagi, 2, ',', '.') ?> ₺
                                                        </span>
                                                    </td>
                                                    <td class="text-end text-danger fw-medium">
                                                        <?php if ($icraKesintisi > 0): ?>
                                                            <span class="btn-icra-detail cursor-pointer text-decoration-underline"
                                                                data-id="<?= $personel->id ?>" title="İcra Detaylarını Gör">
                                                                <?= number_format($icraKesintisi, 2, ',', '.') . ' ₺' ?>
                                                            </span>
                                                        <?php else: ?>
                                                            -
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="text-end text-primary">
                                                        <?= $bankaOdemesi > 0 ? number_format($bankaOdemesi, 2, ',', '.') . ' ₺' : '-' ?>
                                                    </td>
                                                    <td class="text-end text-info td-sodexo" style="width: 150px;">
                                                        <div
                                                            class="sodexo-wrapper d-flex align-items-center justify-content-end gap-2">
                                                            <span class="sodexo-value fw-bold">
                                                                <?= $sodexoOdemesi > 0 ? number_format($sodexoOdemesi, 2, ',', '.') . ' ₺' : '-' ?>
                                                            </span>
                                                            <input type="text"
                                                                class="form-control form-control-sm text-end update-sodexo money d-none"
                                                                style="width: 100px;" data-id="<?= $personel->id ?>"
                                                                data-net="<?= number_format($netAlacagi, 2, '.', '') ?>"
                                                                data-banka="<?= number_format($bankaOdemesi, 2, '.', '') ?>"
                                                                data-diger="<?= number_format($personel->diger_odeme ?? 0, 2, '.', '') ?>"
                                                                data-icra="<?= number_format($icraKesintisi, 2, '.', '') ?>"
                                                                data-current-val="<?= $sodexoOdemesi ?>"
                                                                value="<?= Helper::formattedMoney($sodexoOdemesi) ?>">
                                                            <a href="javascript:void(0);" class="btn-edit-sodexo-inline text-muted"
                                                                title="Düzenle">
                                                                <i data-feather="edit-3" style="width: 14px; height: 14px;"></i>
                                                            </a>
                                                        </div>
                                                    </td>
                                                    <td class="text-end text-warning fw-bold td-elden">
                                                        <?= $eldenOdeme > 0 ? number_format($eldenOdeme, 2, ',', '.') . ' ₺' : '-' ?>
                                                    </td>
                                                    <td class="text-center">
                                                        <div class="dropdown">
                                                            <button class="btn btn-sm btn-secondary dropdown-toggle" type="button"
                                                                data-bs-toggle="dropdown" data-bs-boundary="viewport"
                                                                aria-expanded="false">
                                                                <i class="bx bx-dots-vertical-rounded"></i>
                                                            </button>
                                                            <ul class="dropdown-menu dropdown-menu-end">
                                                                <li>
                                                                    <a class="dropdown-item btn-odeme<?= $donemKapali ? ' disabled' : '' ?>"
                                                                        href="javascript:void(0);" data-id="<?= $personel->id ?>"
                                                                        data-net="<?= $netAlacagi ?>"
                                                                        data-banka="<?= $bankaOdemesi ?>"
                                                                        data-sodexo="<?= $sodexoOdemesi ?>"
                                                                        data-diger="<?= $personel->diger_odeme ?? 0 ?>"
                                                                        data-icra="<?= $icraKesintisi ?>"
                                                                        data-ad="<?= htmlspecialchars($personel->adi_soyadi) ?>">
                                                                        <i class="mdi mdi-wallet-outline me-2 text-primary"></i>
                                                                        Ödeme
                                                                        Dağıt
                                                                    </a>
                                                                </li>
                                                                <li>
                                                                    <a class="dropdown-item btn-detail" href="javascript:void(0);"
                                                                        data-id="<?= $personel->id ?>">
                                                                        <i class="mdi mdi-information-outline me-2 text-info"></i>
                                                                        Detay
                                                                    </a>
                                                                </li>
                                                                <li>
                                                                </li>
                                                                <li>
                                                                    <a class="dropdown-item btn-gelir-ekle<?= $donemKapali ? ' disabled' : '' ?>"
                                                                        href="javascript:void(0);"
                                                                        data-id="<?= $personel->personel_id ?>"
                                                                        data-ad="<?= htmlspecialchars($personel->adi_soyadi) ?>">
                                                                        <i
                                                                            class="mdi mdi-plus-circle-outline me-2 text-success"></i>
                                                                        Gelir Ekle
                                                                    </a>
                                                                </li>
                                                                <li>
                                                                    <a class="dropdown-item btn-kesinti-ekle<?= $donemKapali ? ' disabled' : '' ?>"
                                                                        href="javascript:void(0);"
                                                                        data-id="<?= $personel->personel_id ?>"
                                                                        data-ad="<?= htmlspecialchars($personel->adi_soyadi) ?>"
                                                                        data-maas="<?= floatval($personel->maas_tutari ?? 0) ?>"
                                                                        data-maas-durumu="<?= $personel->maas_durumu ?? '' ?>">
                                                                        <i
                                                                            class="mdi mdi-minus-circle-outline me-2 text-danger"></i>
                                                                        Kesinti Ekle
                                                                    </a>
                                                                </li>
                                                                <li>
                                                                </li>
                                                                <li>
                                                                    <a class="dropdown-item btn-remove text-danger<?= $donemKapali ? ' disabled' : '' ?>"
                                                                        href="javascript:void(0);" data-id="<?= $personel->id ?>"
                                                                        data-ad="<?= htmlspecialchars($personel->adi_soyadi) ?>">
                                                                        <i class="mdi mdi-trash-can-outline me-2"></i> Dönemden
                                                                        Çıkar
                                                                    </a>
                                                                </li>
                                                            </ul>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
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
                                                <?= Form::FormFloatInput("date", "tarih", date('Y-m-d'), "", "Tarih", "calendar", "form-control", true, null, "off", false, 'id="gelir_tarih"') ?>
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
                                                <?= Form::FormFloatInput("date", "tarih", date('Y-m-d'), "", "Tarih", "calendar", "form-control", true, null, "off", false, 'id="kesinti_tarih"') ?>
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
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title"><i class="bx bx-show me-2"></i>Bordro Detayı</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="bordroDetailContent">
                    <!-- İçerik AJAX ile yüklenecek -->
                </div>
                <div class="modal-footer">
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

</div>

<script src="views/bordro/js/bordro.js?v=<?= time() ?>"></script>