<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Admin kontrolü
if (!isset($_SESSION['id']) || !isset($_SESSION['firma_id'])) {
    die('Yetkisiz erişim.');
}

require_once dirname(__DIR__, 2) . '/Autoloader.php';

use App\Model\BordroPersonelModel;
use App\Model\PersonelModel;
use App\Model\FirmaModel;
use Mpdf\Mpdf;

$bordro_id = intval($_GET['id'] ?? 0);
$personel_id = intval($_GET['personel_id'] ?? 0);
$donem_id = intval($_GET['donem'] ?? 0);

$BordroModel = new BordroPersonelModel();
$PersonelModel = new PersonelModel();
$FirmaModel = new FirmaModel();

$bordroListesi = [];

if ($donem_id > 0) {
    // Toplu basım
    $bordroListesi = $BordroModel->getPersonellerByDonem($donem_id);
} elseif ($bordro_id > 0 && $personel_id > 0) {
    // Tekli basım
    $bordro = $BordroModel->find($bordro_id);
    if ($bordro) {
        $bordroListesi[] = $bordro;
    }
}

if (empty($bordroListesi)) {
    die('Geçersiz parametre veya kayıt bulunamadı.');
}

$firma = $FirmaModel->find($_SESSION['firma_id']);
if (!$firma) {
    die('Firma kaydı bulunamadı.');
}

// Ortak Stiller (Resimdeki stile uygun olarak güncellendi)
$style = '
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
    .mb-5 { margin-bottom: 5px; }
    
    .bg-light { background-color: #f9f9f9; }
';

$htmlBody = '';
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
    'December' => 'Aralık'
];

foreach ($bordroListesi as $index => $bordro) {
    $personel_id = $bordro->personel_id;
    $personel = $PersonelModel->find($personel_id);

    if (!$personel || $personel->firma_id != $_SESSION['firma_id']) {
        continue;
    }

    $db = $BordroModel->getDb();
    
    // Bordro donem tarihlerini alalım
    $sqlDonemDates = $db->prepare("SELECT baslangic_tarihi, bitis_tarihi, donem_adi FROM bordro_donemi WHERE id = ?");
    $sqlDonemDates->execute([$bordro->donem_id]);
    $donemData = $sqlDonemDates->fetch(PDO::FETCH_OBJ);
    
    $baslangic_tarihi_ref = $donemData->baslangic_tarihi ?? date('Y-m-01');
    $donemYil = date('Y', strtotime($baslangic_tarihi_ref));
    $donemAyNum = date('m', strtotime($baslangic_tarihi_ref));
    $donemAyAdi = strtr(date('F', strtotime($baslangic_tarihi_ref)), $aylar);
    $donemText = $donemAyNum . '/' . $donemYil;

    $hesaplamaDetay = !empty($bordro->hesaplama_detay) ? json_decode($bordro->hesaplama_detay, true) : [];
    
    // Matrahlar ve Günler
    $normalGun = $hesaplamaDetay['matrahlar']['normal_gun'] ?? 0;
    $haftaTatili = $hesaplamaDetay['matrahlar']['hafta_tatili_gunu'] ?? 0;
    $genelTatil = $hesaplamaDetay['matrahlar']['genel_tatil_gunu'] ?? 0;
    $ucretliIzin = $hesaplamaDetay['matrahlar']['ucretli_izin_gunu'] ?? 0;
    $raporGun = $hesaplamaDetay['matrahlar']['rapor_gunu'] ?? 0;
    $ucretsizIzinGunu = $hesaplamaDetay['matrahlar']['ucretsiz_izin_gunu'] ?? 0;
    $sskGun = $hesaplamaDetay['matrahlar']['ssk_gunu'] ?? ($bordro->calisan_gun ?? 30);

    // SSK Gün ile diğer günlerin uyumlu olması için Normal Günü her zaman (SSK - Kesilen Günler) olarak hesaplarız.
    $normalGun = max(0, $sskGun - $haftaTatili - $genelTatil - $ucretliIzin - $raporGun - $ucretsizIzinGunu);
    
    // Kazançları günlere göre orantılayalım (Genelde Toplam Brüt / SSK Gün * Tür Gün)
    $gunlukUcret = ($sskGun > 0) ? ($bordro->brut_maas / $sskGun) : 0;
    
    $normalTutar = round($gunlukUcret * $normalGun, 2);
    $haftaTatiliTutar = round($gunlukUcret * $haftaTatili, 2);
    $genelTatilTutar = round($gunlukUcret * $genelTatil, 2);
    $ucretliIzinTutar = round($gunlukUcret * $ucretliIzin, 2);
    
    $raporTutar = 0; 
    $toplamTutarGun = $normalTutar + $haftaTatiliTutar + $genelTatilTutar + $ucretliIzinTutar;

    // Yasal Kesintiler Detay
    $sgkMatrah = $hesaplamaDetay['matrahlar']['sgk_matrahi'] ?? ($bordro->brut_maas + ($hesaplamaDetay['ozet']['sgk_matrah_ekleri'] ?? 0));
    $gelirVergisiMatrah = $hesaplamaDetay['matrahlar']['gelir_vergisi_matrahi'] ?? 0;
    $sgkIsci = $bordro->sgk_isci ?? 0;
    $sgkIsveren = $bordro->sgk_isveren ?? 0;
    $issizlikIsci = $bordro->issizlik_isci ?? 0;
    $issizlikIsveren = $bordro->issizlik_isveren ?? 0;
    $gelirVergisi = $bordro->gelir_vergisi ?? 0;
    $damgaVergisi = $bordro->damga_vergisi ?? 0;
    
    $istisnaGV = $hesaplamaDetay['indirimler']['asgari_ucret_istisna_gv'] ?? 0;
    $istisnaDV = $hesaplamaDetay['indirimler']['asgari_ucret_istisna_dv'] ?? 0;
    $oncekiAyMatrah = $hesaplamaDetay['matrahlar']['onceki_kumulatif'] ?? 0;
    $yilIciToplam = $hesaplamaDetay['matrahlar']['yeni_kumulatif'] ?? $gelirVergisiMatrah + $oncekiAyMatrah;

    // Ek Kazançlar ve Özel Kesintiler
    $kesintilerDetay = $BordroModel->getDonemKesintileriListe($personel_id, $bordro->donem_id);
    $ekOdemelerDetay = $BordroModel->getDonemEkOdemeleriListe($personel_id, $bordro->donem_id);

    // Ek Ödemeleri Grupla (Özellikle puantaj kalemlerini)
    $groupedEkOdemeler = [];
    foreach ($ekOdemelerDetay as $ek) {
        $desc = $ek->aciklama;
        
        if (strpos($desc, '[Puantaj]') === 0) {
            $key = 'puantaj_toplam';
            if (!isset($groupedEkOdemeler[$key])) {
                $groupedEkOdemeler[$key] = [
                    'aciklama' => 'Puantaj Ödemeleri',
                    'toplam' => 0
                ];
            }
            $groupedEkOdemeler[$key]['toplam'] += floatval($ek->tutar);
        } else {
            $cleanedDesc = $desc;
            if (strpos($desc, '[Yemek Yardımı]') !== false) $cleanedDesc = 'Yemek Yardımı';
            if (strpos($desc, '[Eş Yardımı]') !== false) $cleanedDesc = 'Aile Yardımı';

            $groupedEkOdemeler[] = [
                'aciklama' => $cleanedDesc,
                'toplam' => floatval($ek->tutar)
            ];
        }
    }

    $toplamEkKazanc = 0;
    foreach($ekOdemelerDetay as $ek) $toplamEkKazanc += $ek->tutar;

    $toplamOzelKesinti = 0;
    foreach($kesintilerDetay as $k) $toplamOzelKesinti += $k->tutar;

    $toplamYasalKesinti = $sgkIsci + $issizlikIsci + $gelirVergisi + $damgaVergisi;
    $toplamKesinti = $toplamYasalKesinti + $toplamOzelKesinti;

    $htmlBody .= '
    <div class="bordro-container">
        <div class="main-title">ÜCRET HESAP PUSULASI</div>
        
        <table class="info-table">
            <tr>
                <td class="info-label">Ad Soyad</td><td class="info-sep">:</td><td width="250">' . ($personel->adi_soyadi ?? '-') . '</td>
                <td class="info-label">Bordro Tür</td><td class="info-sep">:</td><td>' . ($bordro->bordro_turu ?? 'Maaş') . '</td>
            </tr>
            <tr>
                <td class="info-label">İşyeri</td><td class="info-sep">:</td><td>' . ($firma->firma_adi ?? 'ERSAN ELEKTRİK') . '</td>
                <td class="info-label">İşyeri No</td><td class="info-sep">:</td><td>' . (!empty($firma->sgk_no) && $firma->sgk_no !== '0' ? $firma->sgk_no : '-') . '</td>
            </tr>
            <tr>
                <td class="info-label">Görevi</td><td class="info-sep">:</td><td>' . ($personel->gorev ?? '-') . '</td>
                <td class="info-label">Vergi Dairesi No</td><td class="info-sep">:</td><td>' . (!empty($firma->vergi_dairesi) ? $firma->vergi_dairesi : '') . (!empty($firma->vergi_no) && $firma->vergi_no !== '0' ? ' / ' . $firma->vergi_no : (!empty($firma->vergi_dairesi) ? '' : '-')) . '</td>
            </tr>
            <tr>
                <td class="info-label">Dönem</td><td class="info-sep">:</td><td>' . $donemText . '</td>
                <td class="info-label">Mersis No</td><td class="info-sep">:</td><td>' . (!empty($firma->mersis_no) && $firma->mersis_no !== '0' ? $firma->mersis_no : '-') . '</td>
            </tr>
            <tr>
                <td class="info-label">Adres</td><td class="info-sep">:</td><td>' . (!empty($firma->adres) && $firma->adres !== '0' ? $firma->adres : '-') . '</td>
                <td class="info-label">Ticaret Sicil No</td><td class="info-sep">:</td><td>' . (!empty($firma->sicil_no) && $firma->sicil_no !== '0' ? $firma->sicil_no : '-') . '</td>
            </tr>
            <tr>
                <td class="info-label">Merkez Adres</td><td class="info-sep">:</td><td>' . (!empty($firma->merkez_adres) && $firma->merkez_adres !== '0' ? $firma->merkez_adres : (!empty($firma->adres) && $firma->adres !== '0' ? $firma->adres : '-')) . '</td>
                <td class="info-label">Vatandaş No</td><td class="info-sep">:</td><td>' . ($personel->tc_kimlik_no ?? '-') . '</td>
            </tr>
            <tr>
                <td class="info-label">Web Adresi</td><td class="info-sep">:</td><td>' . (!empty($firma->web_adresi) && $firma->web_adresi !== '0' ? $firma->web_adresi : '-') . '</td>
                <td class="info-label">SSK No</td><td class="info-sep">:</td><td>-</td>
            </tr>
            <tr>
                <td class="info-label"></td><td class="info-sep"></td><td></td>
                <td class="info-label">Giriş Tarihi</td><td class="info-sep">:</td><td>' . ($personel->ise_giris_tarihi ? date('d.m.Y', strtotime($personel->ise_giris_tarihi)) : '-') . '</td>
            </tr>
            <tr>
                <td class="info-label"></td><td class="info-sep"></td><td></td>
                <td class="info-label">Çıkış Tarihi</td><td class="info-sep">:</td><td>' . (!empty($personel->isten_cikis_tarihi) && $personel->isten_cikis_tarihi != '0000-00-00' ? date('d.m.Y', strtotime($personel->isten_cikis_tarihi)) : '-') . '</td>
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
                    <td class="text-center">' . $sskGun . '</td>
                    <td class="text-center">' . $normalGun . '</td>
                    <td class="text-center">' . $haftaTatili . '</td>
                    <td class="text-center">' . $genelTatil . '</td>
                    <td class="text-center">' . $ucretliIzin . '</td>
                    <td class="text-center">' . ($raporGun + $ucretsizIzinGunu) . '</td>
                </tr>
                <tr>
                    <td class="text-bold">Toplam Tutar</td>
                    <td class="text-right">' . number_format($bordro->brut_maas, 2, ',', '.') . '</td>
                    <td class="text-right">' . ($normalTutar > 0 ? number_format($normalTutar, 2, ',', '.') : '') . '</td>
                    <td class="text-right">' . ($haftaTatiliTutar > 0 ? number_format($haftaTatiliTutar, 2, ',', '.') : '') . '</td>
                    <td class="text-right">' . ($genelTatilTutar > 0 ? number_format($genelTatilTutar, 2, ',', '.') : '') . '</td>
                    <td class="text-right">' . ($ucretliIzinTutar > 0 ? number_format($ucretliIzinTutar, 2, ',', '.') : '') . '</td>
                    <td class="text-right"></td>
                </tr>
            </tbody>
        </table>
        
        <div style="margin: 3px 0; font-weight: bold;">Eksik Gün: ' . 
            (($raporGun > 0 || $ucretsizIzinGunu > 0) 
                ? ($raporGun > 0 ? $raporGun . ' GÜN RAPORLU ' : '') . ($ucretsizIzinGunu > 0 ? $ucretsizIzinGunu . ' GÜN ÜCRETSİZ İZİNLİ' : '') 
                : 'YOK') . 
        '</div>';


        $htmlBody .= '<table class="breakdown-container">
            <tr>
                <td class="breakdown-left">
                    <div class="section-title">YASAL KESİNTİLER</div>
                    <table class="border-table">
                        <tr>
                            <td rowspan="3" width="20%" class="text-center text-bold">SİGORTA</td>
                            <td width="50%">Matrah</td>
                            <td width="30%" class="text-right">' . number_format($sgkMatrah, 2, ',', '.') . '</td>
                        </tr>
                        <tr>
                            <td>İşçi Prim Tutarı</td>
                            <td class="text-right">' . number_format($sgkIsci, 2, ',', '.') . '</td>
                        </tr>
                        <tr>
                            <td>İşveren Prim Tutarı</td>
                            <td class="text-right">' . number_format($sgkIsveren, 2, ',', '.') . '</td>
                        </tr>
                        <tr>
                            <td rowspan="6" class="text-center text-bold">VERGİ</td>
                            <td>Matrah</td>
                            <td class="text-right">' . number_format($gelirVergisiMatrah, 2, ',', '.') . '</td>
                        </tr>
                        <tr>
                            <td>Gelir Vergisi</td>
                            <td class="text-right">' . number_format($gelirVergisi, 2, ',', '.') . '</td>
                        </tr>
                        <tr>
                            <td>İndirim</td>
                            <td class="text-right">0,00</td>
                        </tr>
                        <tr>
                            <td>Önceki Ay Matrah</td>
                            <td class="text-right">' . number_format($oncekiAyMatrah, 2, ',', '.') . '</td>
                        </tr>
                        <tr>
                            <td>Yıl İçi Toplam</td>
                            <td class="text-right">' . number_format($yilIciToplam, 2, ',', '.') . '</td>
                        </tr>
                        <tr>
                            <td>Damga Vergisi</td>
                            <td class="text-right">' . number_format($damgaVergisi, 2, ',', '.') . '</td>
                        </tr>
                        <tr>
                            <td rowspan="2" class="text-center text-bold">İŞSİZLİK</td>
                            <td>İşçi Prim Tutarı</td>
                            <td class="text-right">' . number_format($issizlikIsci, 2, ',', '.') . '</td>
                        </tr>
                        <tr>
                            <td>İşveren Prim Tutarı</td>
                            <td class="text-right">' . number_format($issizlikIsveren, 2, ',', '.') . '</td>
                        </tr>
                    </table>
                </td>
                <td class="breakdown-right">
                    <div class="section-title">EK KAZANÇLAR</div>
                    <table class="border-table">
                        ';
    if(empty($groupedEkOdemeler)) {
        $htmlBody .= '<tr><td height="40" colspan="3"></td></tr>';
    } else {
        foreach($groupedEkOdemeler as $ek) {
            $htmlBody .= '<tr>
                <td width="70%">' . $ek['aciklama'] . '</td>
                <td width="10%" class="text-center"></td>
                <td width="20%" class="text-right">' . number_format($ek['toplam'], 2, ',', '.') . ' ₺</td>
            </tr>';
        }
    }
    
    $htmlBody .= '
                    </table>
                    
                    <div class="section-title mt-10">ÖZEL KESİNTİLER</div>
                    <table class="border-table">
                        ';
    if(empty($kesintilerDetay)) {
        $htmlBody .= '<tr><td height="40" colspan="2"></td></tr>';
    } else {
        foreach($kesintilerDetay as $k) {
            $htmlBody .= '<tr>
                <td width="80%">' . $k->aciklama . '</td>
                <td width="20%" class="text-right">' . number_format($k->tutar, 2, ',', '.') . ' ₺</td>
            </tr>';
        }
    }
    
    $htmlBody .= '
                    </table>
                </td>
            </tr>
        </table>

        <table class="breakdown-container mt-10">
            <tr>
                <td width="50%" valign="top">
                    <div class="section-title">ÖZET BİLGİLER</div>
                    <table class="border-table">
                        <tr><td width="70%">Kazanç Toplam</td><td width="30%" class="text-right">' . number_format($bordro->brut_maas + $toplamEkKazanc, 2, ',', '.') . '</td></tr>
                        <tr><td>Yasal Kesinti Toplamı</td><td class="text-right">' . number_format($toplamYasalKesinti, 2, ',', '.') . '</td></tr>
                        <tr><td>Kesintiler Toplamı</td><td class="text-right">' . number_format($toplamKesinti, 2, ',', '.') . '</td></tr>
                        <tr><td>Özel Kesinti Toplamı</td><td class="text-right">' . number_format($toplamOzelKesinti, 2, ',', '.') . '</td></tr>
                    </table>
                    
                    <table class="border-table mt-10">
                        <tr><td width="70%">Asgari Ücret Gelir Vergisi</td><td width="30%" class="text-right">' . number_format($istisnaGV, 2, ',', '.') . '</td></tr>
                        <tr><td>Asgari Ücret Damga Vergisi</td><td class="text-right">' . number_format($istisnaDV, 2, ',', '.') . '</td></tr>
                        <tr class="bg-light"><td class="text-bold">Net Ödenen</td><td class="text-right text-bold">' . number_format($bordro->net_maas, 2, ',', '.') . '</td></tr>
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
            ' . $donemYil . ' YILI ' . mb_convert_case($donemAyAdi, MB_CASE_UPPER, "UTF-8") . ' AYINA AİT, ADIMA TAHAKKUK EDEN YUKARIDA YAZILI GELİRLERE KARŞILIK NET TUTARIN TAMAMINI NAKDEN ALDIM.
        </div>
    </div>';

    if ($index < count($bordroListesi) - 1) {
        $htmlBody .= '<div style="page-break-after: always;"></div>';
    }
}

$html = '
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <style>' . $style . '</style>
</head>
<body>
    ' . $htmlBody . '
</body>
</html>';

$fileName = 'bordro_listesi_' . date('Y_m_d_H_i') . '.pdf';
if (count($bordroListesi) === 1) {
    $tekilPersonel = $PersonelModel->find($bordroListesi[0]->personel_id);
    $fileName = 'bordro_' . str_replace(' ', '_', $tekilPersonel->adi_soyadi ?? 'personel') . '_' . date('Y_m', strtotime($bordroListesi[0]->baslangic_tarihi ?? 'now')) . '.pdf';
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
