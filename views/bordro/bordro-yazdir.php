<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['id']) || !isset($_SESSION['firma_id'])) {
    die('Yetkisiz erişim.');
}

require_once dirname(__DIR__, 2) . '/Autoloader.php';

use App\Model\BordroDonemModel;
use App\Model\BordroParametreModel;
use App\Model\BordroPersonelModel;
use App\Model\FirmaModel;
use App\Model\PersonelModel;
use Mpdf\Mpdf;

$bordro_id = intval($_GET['id'] ?? 0);
$personel_id = intval($_GET['personel_id'] ?? 0);
$donem_id = intval($_GET['donem'] ?? 0);

$BordroDonemModel = new BordroDonemModel();
$BordroParametreModel = new BordroParametreModel();
$BordroModel = new BordroPersonelModel();
$PersonelModel = new PersonelModel();
$FirmaModel = new FirmaModel();

$bordroListesi = [];
$donemBilgi = null;

if ($donem_id > 0) {
    $donemBilgi = $BordroDonemModel->getDonemById($donem_id);
    if ($donemBilgi) {
        $bordroListesi = $BordroModel->getPersonellerByDonem($donem_id);
    }
} elseif ($bordro_id > 0 && $personel_id > 0) {
    $bordroKaydi = $BordroModel->find($bordro_id);
    if ($bordroKaydi && intval($bordroKaydi->personel_id ?? 0) === $personel_id) {
        $donemBilgi = $BordroDonemModel->getDonemById($bordroKaydi->donem_id);
        if ($donemBilgi) {
            $bordroListesi = $BordroModel->getPersonellerByDonem($bordroKaydi->donem_id, [$bordro_id]);
        }
    }
}

if (empty($bordroListesi)) {
    die('Geçersiz parametre veya kayıt bulunamadı.');
}

$firma = $FirmaModel->find($_SESSION['firma_id']);
if (!$firma) {
    die('Firma kaydı bulunamadı.');
}

$style = <<<'CSS'
body { font-family: "DejaVu Sans", sans-serif; color: #000; font-size: 8pt; line-height: 1.2; padding: 0; margin: 0; }
.bordro-container { width: 100%; }
.main-title { text-align: center; font-size: 11pt; font-weight: bold; margin-bottom: 10px; text-transform: uppercase; }
table { width: 100%; border-collapse: collapse; margin-bottom: 5px; }
th, td { padding: 3px 5px; vertical-align: middle; }
.border-table th, .border-table td { border: 1px solid #000; }
.border-table th { background: #fff; font-weight: bold; text-align: center; }
.info-table td { border: none; padding: 1px 5px; }
.info-label { font-weight: bold; width: 120px; }
.info-sep { width: 10px; text-align: center; }
.section-title { font-weight: bold; text-align: center; background: #fff; border: 1px solid #000; padding: 2px; text-transform: uppercase; }
.text-center { text-align: center; }
.text-right { text-align: right; }
.text-bold { font-weight: bold; }
.breakdown-container { width: 100%; border-spacing: 0; }
.breakdown-left { width: 50%; padding-right: 5px; vertical-align: top; }
.breakdown-right { width: 50%; padding-left: 5px; vertical-align: top; }
.footer-text { margin-top: 50px; font-size: 8pt; text-align: left; }
.mt-10 { margin-top: 10px; }
.bg-light { background-color: #f9f9f9; }
CSS;

$aylar = [
    'January' => 'Ocak',
    'February' => 'Şubat',
    'March' => 'Mart',
    'April' => 'Nisan',
    'May' => 'Mayıs',
    'June' => 'Haziran',
    'July' => 'Temmuz',
    'August' => 'Ağustos',
    'September' => 'Eylül',
    'October' => 'Ekim',
    'November' => 'Kasım',
    'December' => 'Aralık',
];

$kesintiTurEtiketleri = [
    'icra' => 'İcra',
    'avans' => 'Avans',
    'nafaka' => 'Nafaka',
    'ceza' => 'Ceza',
    'izin_kesinti' => 'Ücretsiz İzin',
    'bes_kesinti' => 'BES Kesintisi',
    'diger' => 'Diğer Kesinti',
];

$ekOdemeTurEtiketleri = [
    'prim' => 'Prim',
    'mesai' => 'Fazla Mesai',
    'ikramiye' => 'İkramiye',
    'yol' => 'Yol Yardımı',
    'yemek' => 'Yemek Yardımı',
    'hafta_ici_nobet' => 'Hafta İçi Nöbet',
    'hafta_sonu_nobet' => 'Hafta Sonu Nöbet',
    'resmi_tatil_nobet' => 'Resmi Tatil Nöbeti',
    'nobet_grubu' => 'Nöbet Ödemeleri',
    'diger' => 'Diğer Ek Ödeme',
    'puantaj_toplam' => 'Puantaj Ödemeleri',
    'kacak_kontrol' => 'Kaçak Kontrol',
    'yemek_maasa_dahil' => 'Yemek Yardımı (Maaşa Dahil)',
];

$formatMoney = static fn(float $value): string => number_format($value, 2, ',', '.');

$buildKesintiGroups = static function (array $kayitlar, array $etiketler): array {
    $groups = [];

    foreach ($kayitlar as $kayit) {
        if (($kayit->tur ?? '') === 'izin_kesinti') {
            continue;
        }

        $label = $etiketler[$kayit->tur] ?? ucfirst((string) ($kayit->tur ?? 'Kesinti'));
        if (!isset($groups[$label])) {
            $groups[$label] = [
                'aciklama' => $label,
                'toplam' => 0.0,
                'adet' => 0,
            ];
        }

        $groups[$label]['toplam'] += floatval($kayit->tutar ?? 0);
        $groups[$label]['adet']++;
    }

    uasort($groups, static fn(array $a, array $b): int => $b['toplam'] <=> $a['toplam']);

    return array_values($groups);
};

$buildEkOdemeGroups = static function (array $kayitlar, array $hesaplamaDetay, array $etiketler): array {
    $groups = [];
    $jsonByCode = [];
    $ozet = is_array($hesaplamaDetay['ozet'] ?? null) ? $hesaplamaDetay['ozet'] : [];

    foreach (($hesaplamaDetay['ek_odemeler'] ?? []) as $jsonOdeme) {
        $kod = $jsonOdeme['kod'] ?? null;
        if ($kod === null) {
            continue;
        }
        $jsonByCode[$kod][] = $jsonOdeme;
    }

    foreach ($kayitlar as $kayit) {
        $aciklama = (string) ($kayit->aciklama ?? '');
        $tur = (string) ($kayit->tur ?? '');
        $groupKey = $tur;

        if (strpos($aciklama, '[Puantaj]') === 0) {
            $groupKey = 'puantaj_toplam';
        } elseif (strpos($aciklama, '[Kaçak Kontrol]') === 0) {
            $groupKey = 'kacak_kontrol';
        } elseif (strpos($tur, 'nobet') !== false) {
            $groupKey = 'nobet_grubu';
        }

        $hesaplananTutar = floatval($kayit->tutar ?? 0);
        $hesaplananAdet = 0;

        if (preg_match('/\((\d+)\s*Adet/i', $aciklama, $adetMatch)) {
            $hesaplananAdet = intval($adetMatch[1]);
        }

        if (isset($jsonByCode[$tur]) && !empty($jsonByCode[$tur])) {
            $jsonOdeme = array_shift($jsonByCode[$tur]);
            $hesaplananTutar = floatval($jsonOdeme['hesaplanan_tutar'] ?? $jsonOdeme['tutar'] ?? $hesaplananTutar);
            $hesaplananAdet = intval($jsonOdeme['gun_sayisi'] ?? $hesaplananAdet);
        }

        if (!isset($groups[$groupKey])) {
            $groups[$groupKey] = [
                'aciklama' => $etiketler[$groupKey] ?? ucfirst($groupKey),
                'toplam' => 0.0,
                'adet' => 0,
            ];
        }

        $groups[$groupKey]['toplam'] += $hesaplananTutar;
        $groups[$groupKey]['adet'] += $hesaplananAdet > 0 ? $hesaplananAdet : 1;
    }

    $dahilYemekTutari = floatval($ozet['dahil_yemek_yardimi'] ?? 0);
    if ($dahilYemekTutari > 0) {
        $dahilYemekGun = intval($ozet['dahil_yemek_gun'] ?? 0);
        $dahilYemekGunluk = floatval($ozet['dahil_yemek_gunluk'] ?? 0);
        $groups['yemek_maasa_dahil'] = [
            'aciklama' => $etiketler['yemek_maasa_dahil'] ?? 'Yemek Yardımı (Maaşa Dahil)',
            'toplam' => $dahilYemekTutari,
            'adet' => $dahilYemekGun,
            'detay' => ($dahilYemekGun > 0 && $dahilYemekGunluk > 0)
                ? $dahilYemekGun . ' gün x ' . number_format($dahilYemekGunluk, 2, ',', '.') . ' ₺'
                : null,
        ];
    }

    uasort($groups, static fn(array $a, array $b): int => $b['toplam'] <=> $a['toplam']);

    return array_values($groups);
};

$htmlParts = [];

foreach ($bordroListesi as $bordro) {
    $personel_id = intval($bordro->personel_id ?? 0);
    $personel = $PersonelModel->find($personel_id);

    if (!$personel || intval($personel->firma_id ?? 0) !== intval($_SESSION['firma_id'])) {
        continue;
    }

    $currentDonem = $donemBilgi && intval($donemBilgi->id ?? 0) === intval($bordro->donem_id ?? 0)
        ? $donemBilgi
        : $BordroDonemModel->getDonemById($bordro->donem_id);

    if (!$currentDonem) {
        continue;
    }

    $baslangicTarihi = $currentDonem->baslangic_tarihi ?? date('Y-m-01');
    $donemYil = date('Y', strtotime($baslangicTarihi));
    $donemAyNum = date('m', strtotime($baslangicTarihi));
    $donemAyAdi = strtr(date('F', strtotime($baslangicTarihi)), $aylar);
    $donemText = $donemAyNum . '/' . $donemYil;

    $hesaplamaDetay = !empty($bordro->hesaplama_detay) ? json_decode($bordro->hesaplama_detay, true) : [];
    $hesaplamaDetay = is_array($hesaplamaDetay) ? $hesaplamaDetay : [];
    $matrahlar = is_array($hesaplamaDetay['matrahlar'] ?? null) ? $hesaplamaDetay['matrahlar'] : [];
    $ozetDetay = is_array($hesaplamaDetay['ozet'] ?? null) ? $hesaplamaDetay['ozet'] : [];
    $indirimler = is_array($hesaplamaDetay['indirimler'] ?? null) ? $hesaplamaDetay['indirimler'] : [];

    $asgariUcretNet = floatval($BordroParametreModel->getGenelAyar('asgari_ucret_net', $baslangicTarihi) ?? 17002.12);
    $hesap = $BordroModel->hesaplaOrtakGosterimDegerleri($bordro, $currentDonem, $asgariUcretNet);

    $normalGun = intval($matrahlar['normal_gun'] ?? 0);
    $haftaTatili = intval($matrahlar['hafta_tatili_gunu'] ?? 0);
    $genelTatil = intval($matrahlar['genel_tatil_gunu'] ?? 0);
    $ucretliIzin = intval($matrahlar['ucretli_izin_gunu'] ?? 0);
    $raporGun = intval($matrahlar['rapor_gunu'] ?? 0);
    $ucretsizIzinGunu = intval($matrahlar['ucretsiz_izin_gunu'] ?? ($hesap['ucretsizIzinGunu'] ?? 0));
    $sskGun = intval($matrahlar['ssk_gunu'] ?? ($bordro->calisan_gun ?? $hesap['calismaGunu'] ?? 30));
    $calisanBrutMaas = floatval($matrahlar['calisan_brut_maas'] ?? ($bordro->brut_maas ?? 0));

    $sgkMatrah = floatval($matrahlar['sgk_matrahi'] ?? (floatval($bordro->brut_maas ?? 0) + floatval($ozetDetay['sgk_matrah_ekleri'] ?? 0)));
    $gelirVergisiMatrah = floatval($matrahlar['gelir_vergisi_matrahi'] ?? 0);
    $oncekiAyMatrah = floatval($matrahlar['onceki_kumulatif'] ?? 0);
    $yilIciToplam = floatval($matrahlar['yeni_kumulatif'] ?? ($gelirVergisiMatrah + $oncekiAyMatrah));

    $sgkIsci = floatval($bordro->sgk_isci ?? 0);
    $sgkIsveren = floatval($bordro->sgk_isveren ?? 0);
    $issizlikIsci = floatval($bordro->issizlik_isci ?? 0);
    $issizlikIsveren = floatval($bordro->issizlik_isveren ?? 0);
    $gelirVergisi = floatval($bordro->gelir_vergisi ?? 0);
    $damgaVergisi = floatval($bordro->damga_vergisi ?? 0);

    $toplamAlacak = floatval($hesap['toplamAlacagi'] ?? 0);
    $netAlacak = floatval($hesap['netAlacagi'] ?? 0);
    $netMaasHesap = floatval($hesap['netMaasGercek'] ?? 0);
    $icraKesintisi = floatval($hesap['icraKesintisi'] ?? 0);
    $maasDurumuGosterim = (string) ($hesap['maasDurumu'] ?? ($bordro->maas_durumu ?? ''));

    $asgariMatrarhGoster = !empty($bordro->yemek_yardimi_dahil)
        || stripos($maasDurumuGosterim, 'Net') !== false;

    if ($asgariMatrarhGoster) {
        $asgariHakedisYazdir = round(($asgariUcretNet / 30) * $sskGun, 2);
        $calisanBrutMaas = $asgariHakedisYazdir;
        $sgkMatrah = $asgariHakedisYazdir + floatval($ozetDetay['sgk_matrah_ekleri'] ?? 0);
        $gelirVergisiMatrah = max(0, $sgkMatrah - $sgkIsci - $issizlikIsci + floatval($ozetDetay['vergili_matrah_ekleri'] ?? 0));
        $yilIciToplam = $oncekiAyMatrah + $gelirVergisiMatrah;
    }

    $istisnaGV = floatval($indirimler['asgari_ucret_istisna_gv'] ?? 0);
    $istisnaDV = floatval($indirimler['asgari_ucret_istisna_dv'] ?? 0);

    $kesintiKayitlari = $BordroModel->getDonemKesintileriListe($personel_id, $bordro->donem_id);
    $ekOdemeKayitlari = $BordroModel->getDonemEkOdemeleriListe($personel_id, $bordro->donem_id);

    $groupedKesintiler = $buildKesintiGroups($kesintiKayitlari, $kesintiTurEtiketleri);
    $groupedEkOdemeler = $buildEkOdemeGroups($ekOdemeKayitlari, $hesaplamaDetay, $ekOdemeTurEtiketleri);

    $toplamEkKazanc = floatval($hesap['rawEkOdeme'] ?? 0);
    $hasYemekMaasaDahilRow = false;
    foreach ($groupedEkOdemeler as $ekGroup) {
        if (($ekGroup['aciklama'] ?? '') === ($ekOdemeTurEtiketleri['yemek_maasa_dahil'] ?? 'Yemek Yardımı (Maaşa Dahil)')) {
            $hasYemekMaasaDahilRow = true;
            break;
        }
    }

    if (!empty($bordro->yemek_yardimi_dahil) && intval($bordro->yemek_yardimi_dahil) === 1 && !$hasYemekMaasaDahilRow) {
        $fallbackYemekTutari = 0.0;
        if ($toplamEkKazanc > 0) {
            $fallbackYemekTutari = $toplamEkKazanc;
        } else {
            $fallbackYemekTutari = max(0, round($netAlacak - $calisanBrutMaas, 2));
        }

        if ($fallbackYemekTutari > 0) {
            $fallbackYemekGun = intval($ozetDetay['dahil_yemek_gun'] ?? ($matrahlar['fiili_calisma_gunu'] ?? 0));
            $fallbackYemekGunluk = floatval($ozetDetay['dahil_yemek_gunluk'] ?? 0);
            if ($fallbackYemekGunluk <= 0 && $fallbackYemekGun > 0) {
                $fallbackYemekGunluk = round($fallbackYemekTutari / $fallbackYemekGun, 2);
            }

            $groupedEkOdemeler[] = [
                'aciklama' => $ekOdemeTurEtiketleri['yemek_maasa_dahil'] ?? 'Yemek Yardımı (Maaşa Dahil)',
                'toplam' => $fallbackYemekTutari,
                'adet' => $fallbackYemekGun,
                'detay' => ($fallbackYemekGun > 0 && $fallbackYemekGunluk > 0)
                    ? $fallbackYemekGun . ' gün x ' . number_format($fallbackYemekGunluk, 2, ',', '.') . ' ₺'
                    : null,
            ];

            if ($toplamEkKazanc <= 0) {
                $toplamEkKazanc = $fallbackYemekTutari;
            }
        }
    }

    $toplamOzelKesinti = 0.0;
    foreach ($groupedKesintiler as $kesintiGrubu) {
        $toplamOzelKesinti += floatval($kesintiGrubu['toplam']);
    }

    $toplamYasalKesinti = $sgkIsci + $issizlikIsci + $gelirVergisi + $damgaVergisi;
    $eksikGunMetni = 'YOK';
    if ($raporGun > 0 || $ucretsizIzinGunu > 0) {
        $eksikGunParcalari = [];
        if ($raporGun > 0) {
            $eksikGunParcalari[] = $raporGun . ' GÜN RAPORLU';
        }
        if ($ucretsizIzinGunu > 0) {
            $eksikGunParcalari[] = $ucretsizIzinGunu . ' GÜN ÜCRETSİZ İZİNLİ';
        }
        $eksikGunMetni = implode(' ', $eksikGunParcalari);
    }

    ob_start();
    ?>
    <div class="bordro-container">
        <div class="main-title">ÜCRET HESAP PUSULASI</div>

        <table class="info-table">
            <tr>
                <td class="info-label">Ad Soyad</td><td class="info-sep">:</td><td width="250"><?= htmlspecialchars($personel->adi_soyadi ?? '-') ?></td>
                <td class="info-label">Bordro Tür</td><td class="info-sep">:</td><td><?= htmlspecialchars($bordro->bordro_turu ?? 'Maaş') ?></td>
            </tr>
            <tr>
                <td class="info-label">İşyeri</td><td class="info-sep">:</td><td><?= htmlspecialchars($firma->firma_adi ?? 'ERSAN ELEKTRIK') ?></td>
                <td class="info-label">İşyeri No</td><td class="info-sep">:</td><td><?= !empty($firma->sgk_no) && $firma->sgk_no !== '0' ? htmlspecialchars($firma->sgk_no) : '-' ?></td>
            </tr>
            <tr>
                <td class="info-label">Görevi</td><td class="info-sep">:</td><td><?= htmlspecialchars($personel->gorev ?? '-') ?></td>
                <td class="info-label">Vergi Dairesi No</td><td class="info-sep">:</td><td><?= !empty($firma->vergi_dairesi) ? htmlspecialchars($firma->vergi_dairesi) : '' ?><?= !empty($firma->vergi_no) && $firma->vergi_no !== '0' ? ' / ' . htmlspecialchars($firma->vergi_no) : (!empty($firma->vergi_dairesi) ? '' : '-') ?></td>
            </tr>
            <tr>
                <td class="info-label">Dönem</td><td class="info-sep">:</td><td><?= htmlspecialchars($donemText) ?></td>
                <td class="info-label">Mersis No</td><td class="info-sep">:</td><td><?= !empty($firma->mersis_no) && $firma->mersis_no !== '0' ? htmlspecialchars($firma->mersis_no) : '-' ?></td>
            </tr>
            <tr>
                <td class="info-label">Adres</td><td class="info-sep">:</td><td><?= !empty($firma->adres) && $firma->adres !== '0' ? htmlspecialchars($firma->adres) : '-' ?></td>
                <td class="info-label">Ticaret Sicil No</td><td class="info-sep">:</td><td><?= !empty($firma->sicil_no) && $firma->sicil_no !== '0' ? htmlspecialchars($firma->sicil_no) : '-' ?></td>
            </tr>
            <tr>
                <td class="info-label">Merkez Adres</td><td class="info-sep">:</td><td><?= !empty($firma->merkez_adres) && $firma->merkez_adres !== '0' ? htmlspecialchars($firma->merkez_adres) : (!empty($firma->adres) && $firma->adres !== '0' ? htmlspecialchars($firma->adres) : '-') ?></td>
                <td class="info-label">Vatandaş No</td><td class="info-sep">:</td><td><?= htmlspecialchars($personel->tc_kimlik_no ?? '-') ?></td>
            </tr>
            <tr>
                <td class="info-label">Web Adresi</td><td class="info-sep">:</td><td><?= !empty($firma->web_adresi) && $firma->web_adresi !== '0' ? htmlspecialchars($firma->web_adresi) : '-' ?></td>
                <td class="info-label">SSK No</td><td class="info-sep">:</td><td>-</td>
            </tr>
            <tr>
                <td class="info-label"></td><td class="info-sep"></td><td></td>
                <td class="info-label">Giriş Tarihi</td><td class="info-sep">:</td><td><?= !empty($personel->ise_giris_tarihi) ? date('d.m.Y', strtotime($personel->ise_giris_tarihi)) : '-' ?></td>
            </tr>
            <tr>
                <td class="info-label"></td><td class="info-sep"></td><td></td>
                <td class="info-label">Çıkış Tarihi</td><td class="info-sep">:</td><td><?= !empty($personel->isten_cikis_tarihi) && $personel->isten_cikis_tarihi !== '0000-00-00' ? date('d.m.Y', strtotime($personel->isten_cikis_tarihi)) : '-' ?></td>
            </tr>
        </table>

        <div class="section-title">ÇALIŞMA VE İZİN GÜNLERİ</div>
        <table class="border-table">
            <thead>
                <tr>
                    <th width="14%">Tür</th>
                    <th width="14%">SSK Gün</th>
                    <th width="14%">Normal Gün</th>
                    <th width="14%">Hafta Tatili</th>
                    <th width="14%">Genel Tatil</th>
                    <th width="14%">Ücretli İzin</th>
                    <th width="14%">Rapor / Ü.İ.</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="text-bold">Gün Sayısı</td>
                    <td class="text-center"><?= $sskGun ?></td>
                    <td class="text-center"><?= $normalGun ?></td>
                    <td class="text-center"><?= $haftaTatili ?></td>
                    <td class="text-center"><?= $genelTatil ?></td>
                    <td class="text-center"><?= $ucretliIzin ?></td>
                    <td class="text-center"><?= $raporGun + $ucretsizIzinGunu ?></td>
                </tr>
                <tr>
                    <td class="text-bold">Toplam Tutar</td>
                    <td class="text-right"><?= $formatMoney($calisanBrutMaas) ?></td>
                    <td class="text-right"></td>
                    <td class="text-right"></td>
                    <td class="text-right"></td>
                    <td class="text-right"></td>
                    <td class="text-right"></td>
                </tr>
            </tbody>
        </table>

        <div style="margin: 3px 0; font-weight: bold;">Eksik Gün: <?= htmlspecialchars($eksikGunMetni) ?></div>

        <table class="breakdown-container">
            <tr>
                <td class="breakdown-left">
                    <div class="section-title">YASAL KESİNTİLER</div>
                    <table class="border-table">
                        <tr>
                            <td rowspan="3" width="20%" class="text-center text-bold">SİGORTA</td>
                            <td width="50%">Matrah</td>
                            <td width="30%" class="text-right"><?= $formatMoney($sgkMatrah) ?></td>
                        </tr>
                        <tr>
                            <td>İşçi Prim Tutarı</td>
                            <td class="text-right"><?= $formatMoney($sgkIsci) ?></td>
                        </tr>
                        <tr>
                            <td>İşveren Prim Tutarı</td>
                            <td class="text-right"><?= $formatMoney($sgkIsveren) ?></td>
                        </tr>
                        <tr>
                            <td rowspan="6" class="text-center text-bold">VERGİ</td>
                            <td>Matrah</td>
                            <td class="text-right"><?= $formatMoney($gelirVergisiMatrah) ?></td>
                        </tr>
                        <tr>
                            <td>Gelir Vergisi</td>
                            <td class="text-right"><?= $formatMoney($gelirVergisi) ?></td>
                        </tr>
                        <tr>
                            <td>İndirim</td>
                            <td class="text-right"><?= $formatMoney($istisnaGV) ?></td>
                        </tr>
                        <tr>
                            <td>Önceki Ay Matrah</td>
                            <td class="text-right"><?= $formatMoney($oncekiAyMatrah) ?></td>
                        </tr>
                        <tr>
                            <td>Yıl İçi Toplam</td>
                            <td class="text-right"><?= $formatMoney($yilIciToplam) ?></td>
                        </tr>
                        <tr>
                            <td>Damga Vergisi</td>
                            <td class="text-right"><?= $formatMoney($damgaVergisi) ?></td>
                        </tr>
                        <tr>
                            <td rowspan="2" class="text-center text-bold">İŞSİZLİK</td>
                            <td>İşçi Prim Tutarı</td>
                            <td class="text-right"><?= $formatMoney($issizlikIsci) ?></td>
                        </tr>
                        <tr>
                            <td>İşveren Prim Tutarı</td>
                            <td class="text-right"><?= $formatMoney($issizlikIsveren) ?></td>
                        </tr>
                    </table>
                </td>
                <td class="breakdown-right">
                    <div class="section-title">EK KAZANÇLAR</div>
                    <table class="border-table">
                        <?php if (empty($groupedEkOdemeler)): ?>
                            <tr><td height="40" colspan="3"></td></tr>
                        <?php else: ?>
                            <?php foreach ($groupedEkOdemeler as $ek): ?>
                                <?php $ekAdet = intval($ek['adet'] ?? 0); ?>
                                <?php $ekDetay = trim((string) ($ek['detay'] ?? '')); ?>
                                <tr>
                                    <td width="70%">
                                        <?= htmlspecialchars($ek['aciklama']) ?><?= $ekAdet > 0 && $ekDetay === '' ? ' (' . $ekAdet . ' adet)' : '' ?>
                                        <?php if ($ekDetay !== ''): ?>
                                            <div style="font-size: 7pt; color: #555;"><?= htmlspecialchars($ekDetay) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td width="10%" class="text-center"></td>
                                    <td width="20%" class="text-right"><?= $formatMoney(floatval($ek['toplam'])) ?> ₺</td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        <tr class="bg-light">
                            <td width="70%" class="text-bold">Toplam</td>
                            <td width="10%"></td>
                            <td width="20%" class="text-right text-bold"><?= $formatMoney($toplamEkKazanc) ?> ₺</td>
                        </tr>
                    </table>

                    <div class="section-title mt-10">ÖZEL KESİNTİLER</div>
                    <table class="border-table">
                        <?php if (empty($groupedKesintiler)): ?>
                            <tr><td height="40" colspan="2"></td></tr>
                        <?php else: ?>
                            <?php foreach ($groupedKesintiler as $kesinti): ?>
                                <?php $kesintiAdet = intval($kesinti['adet'] ?? 0); ?>
                                <tr>
                                    <td width="80%"><?= htmlspecialchars($kesinti['aciklama']) ?><?= $kesintiAdet > 1 ? ' (' . $kesintiAdet . ' adet)' : '' ?></td>
                                    <td width="20%" class="text-right"><?= $formatMoney(floatval($kesinti['toplam'])) ?> ₺</td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        <tr class="bg-light">
                            <td width="80%" class="text-bold">Toplam</td>
                            <td width="20%" class="text-right text-bold"><?= $formatMoney($toplamOzelKesinti) ?> ₺</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        <table class="breakdown-container mt-10">
            <tr>
                <td width="50%" valign="top">
                    <div class="section-title">ÖZET BİLGİLER</div>
                    <table class="border-table">
                        <tr><td width="70%">Kazanç Toplam</td><td width="30%" class="text-right"><?= $formatMoney($toplamAlacak) ?></td></tr>
                        <tr><td>Yasal Kesinti Toplamı</td><td class="text-right"><?= $formatMoney($toplamYasalKesinti) ?></td></tr>
                        <tr><td>Özel Kesinti Toplamı</td><td class="text-right"><?= $formatMoney($toplamOzelKesinti) ?></td></tr>
                        <tr><td>Net Alacağı</td><td class="text-right"><?= $formatMoney($netAlacak) ?></td></tr>
                    </table>

                    <table class="border-table mt-10">
                        <tr><td width="70%">Asgari Ücret Gelir Vergisi</td><td width="30%" class="text-right"><?= $formatMoney($istisnaGV) ?></td></tr>
                        <tr><td>Asgari Ücret Damga Vergisi</td><td class="text-right"><?= $formatMoney($istisnaDV) ?></td></tr>
                        <tr><td>İcra Kesintisi</td><td class="text-right"><?= $formatMoney($icraKesintisi) ?></td></tr>
                        <tr class="bg-light"><td class="text-bold">Net Ödenen</td><td class="text-right text-bold"><?= $formatMoney($netMaasHesap) ?></td></tr>
                    </table>
                </td>
                <td width="50%" valign="bottom" align="center">
                    <table style="width: 200px; margin-top: 20px;">
                        <tr><td style="border-top: 1px solid #000; text-align: center; padding-top: 5px;">PERSONEL İMZA</td></tr>
                        <tr><td height="40"></td></tr>
                    </table>
                </td>
            </tr>
        </table>

        <div class="footer-text">
            <?= $donemYil ?> YILI <?= mb_convert_case($donemAyAdi, MB_CASE_UPPER, 'UTF-8') ?> AYINA AİT, ADIMA TAHAKKUK EDEN YUKARIDA YAZILI GELİRLERE KARŞILIK NET TUTARIN TAMAMINI NAKDEN ALDIM.
        </div>
    </div>
    <?php
    $htmlParts[] = ob_get_clean();
}

if (empty($htmlParts)) {
    die('Geçerli kayıt bulunamadı.');
}

$htmlBody = implode('<div style="page-break-after: always;"></div>', $htmlParts);
$html = <<<HTML
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <style>{$style}</style>
</head>
<body>
    {$htmlBody}
</body>
</html>
HTML;

$fileName = 'bordro_listesi_' . date('Y_m_d_H_i') . '.pdf';
if (count($bordroListesi) === 1) {
    $tekilPersonel = $PersonelModel->find($bordroListesi[0]->personel_id);
    $fileName = 'bordro_' . str_replace(' ', '_', $tekilPersonel->adi_soyadi ?? 'personel') . '_' . date('Y_m', strtotime($donemBilgi->baslangic_tarihi ?? 'now')) . '.pdf';
}

$mpdf = new Mpdf([
    'mode' => 'utf-8',
    'format' => 'A4',
    'margin_left' => 10,
    'margin_right' => 10,
    'margin_top' => 10,
    'margin_bottom' => 10,
]);

$mpdf->SetTitle('Ücret Hesap Pusulası');
$mpdf->WriteHTML($html);
$mpdf->Output($fileName, 'I');
exit;
