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

if ($bordro_id <= 0 || $personel_id <= 0) {
    die('Geçersiz parametre.');
}

$BordroModel = new BordroPersonelModel();
$bordro = $BordroModel->find($bordro_id);

if (!$bordro) {
    die('Bordro kaydı bulunamadı.');
}

// Güvenlik kontrolü: Bordro personeli ile istenen personel eşleşiyor mu?
if ($bordro->personel_id != $personel_id) {
    die('Veri tutarsızlığı.');
}

$PersonelModel = new PersonelModel();
$personel = $PersonelModel->find($personel_id);

// Firma kontrolü
if ($personel->firma_id != $_SESSION['firma_id']) {
    die('Yetkisiz firma erişimi.');
}

$FirmaModel = new FirmaModel();
$firma = $FirmaModel->find($_SESSION['firma_id']);

// Ekip ve Bölge bilgilerini getir
$db = $BordroModel->getDb();
$sqlEkip = $db->prepare("
    SELECT 
        GROUP_CONCAT(DISTINCT t.tur_adi SEPARATOR ', ') as ekip_adi,
        GROUP_CONCAT(DISTINCT t.ekip_bolge SEPARATOR ', ') as ekip_bolge
    FROM personel_ekip_gecmisi pg
    JOIN tanimlamalar t ON pg.ekip_kodu_id = t.id
    WHERE pg.personel_id = ? 
    AND pg.baslangic_tarihi <= ? 
    AND (pg.bitis_tarihi IS NULL OR pg.bitis_tarihi >= ?)
    AND pg.firma_id = ?
    GROUP BY pg.personel_id
");
$sqlEkip->execute([$personel_id, $bordro->bitis_tarihi ?? date('Y-m-t'), $bordro->baslangic_tarihi ?? date('Y-m-01'), $_SESSION['firma_id']]);
$ekipBilgisi = $sqlEkip->fetch(PDO::FETCH_OBJ);

$kesintilerDetay = $BordroModel->getDonemKesintileriListe($personel_id, $bordro->donem_id);
$ekOdemelerDetay = $BordroModel->getDonemEkOdemeleriListe($personel_id, $bordro->donem_id);
$guncelKesinti = $BordroModel->getDonemKesintileri($personel_id, $bordro->donem_id);
$guncelEkOdeme = $BordroModel->getDonemEkOdemeleri($personel_id, $bordro->donem_id);

// Hesaplama detaylarını ayrıştır
$hesaplamaDetay = !empty($bordro->hesaplama_detay) ? json_decode($bordro->hesaplama_detay, true) : [];
$calismaGunu = $hesaplamaDetay['matrahlar']['fiili_calisma_gunu'] ?? 30;
$ucretsizIzinGunu = $hesaplamaDetay['matrahlar']['ucretsiz_izin_gunu'] ?? 0;
$ucretliIzinGunu = $hesaplamaDetay['matrahlar']['ucretli_izin_gunu'] ?? 0;
$toplamIzinGunu = $ucretsizIzinGunu + $ucretliIzinGunu;
$icraKesintisi = $hesaplamaDetay['odeme_dagilimi']['icra_kesintisi'] ?? 0;

$toplamYasalKesinti = floatval($bordro->sgk_isci) + floatval($bordro->issizlik_isci) + floatval($bordro->gelir_vergisi) + floatval($bordro->damga_vergisi);
$toplamKesinti = $guncelKesinti + $toplamYasalKesinti;
$toplamEkOdeme = $guncelEkOdeme;

$donemAdi = date('F Y', strtotime($bordro->baslangic_tarihi));
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
$donemAdiTr = strtr($donemAdi, $aylar);

// Birim/Departman Badge Bilgisi
$deptName = $personel->departman ?? '-';
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

// HTML İçeriği
$html = '
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: "DejaVu Sans", sans-serif; color: #333; font-size: 8pt; line-height: 1.4; }
        .header { border-bottom: 2px solid #2563eb; padding-bottom: 8px; margin-bottom: 15px; }
        .logo-box { width: 110px; }
        .company-name { font-size: 13pt; font-weight: bold; color: #1e293b; }
        .document-title { font-size: 11pt; color: #2563eb; text-align: right; font-weight: bold; }
        
        .section-header { 
            background: #f8fafc; 
            padding: 4px 8px; 
            font-weight: bold; 
            color: #1e293b; 
            margin-bottom: 8px; 
            border-left: 3px solid #2563eb; 
            font-size: 8pt;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .info-table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        .info-table td { padding: 2px 5px; vertical-align: top; }
        .label { color: #64748b; width: 90px; font-size: 7pt; text-transform: uppercase; }
        .value { font-weight: bold; color: #1e293b; font-size: 8pt; }
        
        .dept-badge { 
            display: inline-block; 
            padding: 1px 4px; 
            border-radius: 2px; 
            color: #fff; 
            font-weight: bold; 
            font-size: 7pt;
            margin-right: 4px;
        }
        
        .grid-container { width: 100%; border-collapse: separate; border-spacing: 8px 0; margin-bottom: 15px; table-layout: fixed; border-radius: 10px; }
        
        .grid-box { 
            border: 1px solid #cbd5e1; 
            border-radius: 10px; 
            padding: 15px; 
            background: #ffffff;
            vertical-align: top;
        }
        
        .grid-title { 
            font-weight: bold; 
            font-size: 8.5pt; 
            margin-bottom: 8px; 
            padding-bottom: 4px;
            border-bottom: 1px solid #e2e8f0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th { text-align: left; padding: 4px 0; color: #64748b; font-size: 6.5pt; text-transform: uppercase; border-bottom: 1px solid #f1f5f9; }
        .data-table td { padding: 6px 0; border-bottom: 1px solid #f8fafc; font-size: 7.5pt; vertical-align: top; }
        .amount { text-align: right; font-weight: bold; width: 75px; white-space: nowrap; }
        
        .puantaj-detail { font-size: 6.5pt; color: #64748b; margin-top: 2px; line-height: 1.2; font-weight: normal; }
        
        .summary-box { 
            background: #ffffff; 
            border: 1px solid #cbd5e1; 
            padding: 15px; 
            border-radius: 10px; 
            margin-top: 15px;
        }
        
        .net-salary-container { text-align: right; }
        .net-salary-label { font-size: 9pt; font-weight: bold; color: #64748b; }
        .net-salary-value { font-size: 16pt; font-weight: bold; color: #10b981; }
        
        .days-box { 
            background: #f0f9ff; 
            border: 1px solid #bae6fd; 
            padding: 8px; 
            border-radius: 10px; 
            margin-bottom: 15px;
        }
        .day-grid { width: 100%; border-collapse: collapse; }
        .day-item { text-align: center; border-right: 1px solid #bae6fd; width: 25%; }
        .day-item:last-child { border-right: none; }
        .day-label { font-size: 6.5pt; color: #0369a1; text-transform: uppercase; display: block; }
        .day-value { font-size: 10pt; font-weight: bold; color: #0369a1; }
        
        .cost-section { background: #f8fafc; padding: 10px; border-radius: 10px; margin-top: 10px; border: 1px solid #f1f5f9; }
        .cost-table { width: 100%; border-collapse: collapse; }
        .cost-td { text-align: center; padding: 3px; }
        
        .signature-section { margin-top: 40px; width: 100%; }
        .signature-box { border-top: 1px solid #cbd5e1; padding-top: 8px; width: 180px; text-align: center; }
        
        .footer { margin-top: 20px; font-size: 6.5pt; color: #94a3b8; text-align: center; border-top: 1px solid #f1f5f9; padding-top: 8px; }
    </style>
</head>
<body>
    <table width="100%" class="header">
        <tr>
            <td class="logo-box"><img src="' . dirname(__DIR__, 2) . '/assets/images/logo.png" height="30"></td>
            <td>
                <div class="company-name">' . ($firma->firma_adi ?? 'ERSAN ELEKTRİK') . '</div>
                <div style="font-size: 7.5pt; color: #64748b;">Maaş Ödeme Bordrosu (Çalışan Nüshası)</div>
            </td>
            <td class="document-title">' . $donemAdiTr . '</td>
        </tr>
    </table>

    <div class="section-header">Personel Bilgileri</div>
    <table class="info-table">
        <tr>
            <td class="label">Ad Soyad</td>
            <td class="value" width="350">
                <span class="dept-badge" style="background-color: ' . $dInfo['color'] . ';">' . $dInfo['code'] . '</span>
                ' . ($personel->adi_soyadi ?? '-') . '
            </td>
            <td class="label">TC Kimlik</td>
            <td class="value">' . ($personel->tc_kimlik_no ?? '-') . '</td>
        </tr>
        <tr>
            <td class="label">Birim / Görev</td>
            <td class="value"><strong>' . ($personel->departman ?? '-') . '</strong> / ' . ($personel->gorev ?? '-') . '</td>
            <td class="label">İşe Giriş</td>
            <td class="value">' . ($personel->ise_giris_tarihi ? date('d.m.Y', strtotime($personel->ise_giris_tarihi)) : '-') . '</td>
        </tr>
        <tr>
            <td class="label">Ekip / Bölge</td>
            <td class="value">' . ($ekipBilgisi->ekip_adi ?? '-') . ' / ' . ($ekipBilgisi->ekip_bolge ?? '-') . '</td>
            <td class="label">Maaş Tipi</td>
            <td class="value">' . ($personel->maas_durumu ?? 'Brüt') . '</td>
        </tr>
    </table>

    <div class="days-box">
        <table class="day-grid">
            <tr>
                <td class="day-item"><span class="day-label">Fiili Çalışma</span><span class="day-value">' . $calismaGunu . ' Gün</span></td>
                <td class="day-item"><span class="day-label">Ücretli İzin</span><span class="day-value">' . $ucretliIzinGunu . ' Gün</span></td>
                <td class="day-item"><span class="day-label">Ücretsiz İzin</span><span class="day-value">' . $ucretsizIzinGunu . ' Gün</span></td>
                <td class="day-item"><span class="day-label">Hakedilen Gün</span><span class="day-value">' . ($calismaGunu + $ucretliIzinGunu) . ' Gün</span></td>
            </tr>
        </table>
    </div>

    <table class="grid-container">
        <tr>
            <td width="50%" valign="top" class="grid-box">
                <div class="grid-title" style="color: #2563eb;">KAZANÇLAR</div>
                    <table class="data-table">
                        <thead><tr><th>AÇIKLAMA</th><th class="amount">TUTAR</th></tr></thead>
                        <tbody>
                            <tr><td>Brüt Maaş</td><td class="amount">' . number_format($bordro->brut_maas ?? 0, 2, ',', '.') . ' ₺</td></tr>';

if (!empty($ekOdemelerDetay)) {
    foreach ($ekOdemelerDetay as $ek) {
        $desc = $ek->aciklama;
        $sub = "";

        if (preg_match('/^\[Puantaj\]\s*(.*?)\s*\((.*?)\)$/i', $desc, $matches)) {
            $desc = '<strong>' . $matches[1] . '</strong>';
            $sub = '<div class="puantaj-detail">' . $matches[2] . '</div>';
        } else {
            $desc = '<strong>' . (!empty($ek->aciklama) ? $ek->aciklama : (!empty($ek->etiket) ? $ek->etiket : ucfirst($ek->tur))) . '</strong>';
        }

        $html .= '
                        <tr>
                            <td>' . $desc . $sub . '</td>
                            <td class="amount" style="color: #16a34a;">+' . number_format($ek->tutar, 2, ',', '.') . ' ₺</td>
                        </tr>';
    }
}

$html .= '
                        </tbody>
                    </table>
                    <table width="100%" style="margin-top: 10px; border-top: 1px solid #e2e8f0;">
                        <tr>
                            <td style="font-weight: bold; font-size: 8pt; padding-top: 8px;">Toplam Kazanç</td>
                            <td class="amount" style="font-weight: bold; font-size: 8pt; padding-top: 8px;">' . number_format(floatval($bordro->brut_maas) + $toplamEkOdeme, 2, ',', '.') . ' ₺</td>
                        </tr>
                    </table>
            </td>
            <td width="50%" valign="top" class="grid-box">
                <div class="grid-title" style="color: #dc2626;">KESİNTİLER</div>
                    <table class="data-table">
                        <thead><tr><th>AÇIKLAMA</th><th class="amount">TUTAR</th></tr></thead>
                        <tbody>';

$hasYasal = ($personel->maas_durumu ?? '') != 'Prim Usülü';
if ($hasYasal || $bordro->sgk_isci > 0) {
    $html .= '<tr><td>SGK İşçi Payı (%14)</td><td class="amount">-' . number_format($bordro->sgk_isci ?? 0, 2, ',', '.') . ' ₺</td></tr>';
    $html .= '<tr><td>İşsizlik Sigortası (%1)</td><td class="amount">-' . number_format($bordro->issizlik_isci ?? 0, 2, ',', '.') . ' ₺</td></tr>';
    $html .= '<tr><td>Gelir Vergisi</td><td class="amount">-' . number_format($bordro->gelir_vergisi ?? 0, 2, ',', '.') . ' ₺</td></tr>';
    $html .= '<tr><td>Damga Vergisi</td><td class="amount">-' . number_format($bordro->damga_vergisi ?? 0, 2, ',', '.') . ' ₺</td></tr>';
}

if ($icraKesintisi > 0) {
    $html .= '<tr><td><strong>İcra Kesintisi</strong></td><td class="amount" style="color: #dc2626;">-' . number_format($icraKesintisi, 2, ',', '.') . ' ₺</td></tr>';
}

if (!empty($kesintilerDetay)) {
    foreach ($kesintilerDetay as $k) {
        if ($k->tur === 'icra')
            continue;

        $desc = $k->aciklama;
        $sub = "";
        if (preg_match('/^\[(.*?)\]\s*(.*?)\s*\((.*?)\)$/i', $desc, $matches)) {
            $desc = '<strong>[' . $matches[1] . '] ' . $matches[2] . '</strong>';
            $sub = '<div class="puantaj-detail">' . $matches[3] . '</div>';
        }

        $html .= '
                        <tr>
                            <td>' . $desc . $sub . '</td>
                            <td class="amount">-' . number_format($k->tutar, 2, ',', '.') . ' ₺</td>
                        </tr>';
    }
}

$html .= '
                        </tbody>
                    </table>
                    <table width="100%" style="margin-top: 10px; border-top: 1px solid #e2e8f0;">
                        <tr>
                            <td style="font-weight: bold; font-size: 8pt; padding-top: 8px;">Toplam Kesinti</td>
                            <td class="amount" style="font-weight: bold; font-size: 8pt; padding-top: 8px;">-' . number_format($toplamKesinti, 2, ',', '.') . ' ₺</td>
                        </tr>
                    </table>
            </td>
        </tr>
    </table>

    <div class="summary-box">
        <table width="100%">
            <tr>
                <td width="50%" valign="top">
                    <div style="font-weight: bold; color: #64748b; font-size: 7pt; margin-bottom: 5px; text-transform: uppercase;">Ödeme Detayları</div>
                    <table width="100%" style="font-size: 7.5pt;">
                        <tr><td style="color: #64748b; padding: 1px 0;">Banka Hesabı</td><td style="text-align: right; font-weight: bold;">' . number_format($bordro->banka_odemesi ?? 0, 2, ',', '.') . ' ₺</td></tr>
                        <tr><td style="color: #64748b; padding: 1px 0;">Sodexo / Yemek</td><td style="text-align: right; font-weight: bold;">' . number_format($bordro->sodexo_odemesi ?? 0, 2, ',', '.') . ' ₺</td></tr>';

$elden = ($bordro->net_maas ?? 0) - ($bordro->banka_odemesi ?? 0) - ($bordro->sodexo_odemesi ?? 0) - ($bordro->diger_odeme ?? 0);
if ($elden > 0) {
    $html .= '<tr><td style="color: #64748b; padding: 1px 0;">Nakit / Elden</td><td style="text-align: right; font-weight: bold;">' . number_format($elden, 2, ',', '.') . ' ₺</td></tr>';
}

$html .= '
                    </table>
                </td>
                <td width="50%" align="right" valign="bottom">
                    <div class="net-salary-container">
                        <div class="net-salary-label">NET ÖDENECEK</div>
                        <div class="net-salary-value">' . number_format($bordro->net_maas ?? 0, 2, ',', '.') . ' ₺</div>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <div class="cost-section">
        <table class="cost-table">
            <tr>
                <td class="cost-td" width="30%"><span class="day-label">SGK İşveren</span><span class="value" style="font-size: 8pt;">' . number_format($bordro->sgk_isveren ?? 0, 2, ',', '.') . ' ₺</span></td>
                <td class="cost-td" width="30%"><span class="day-label">İşsizlik İşveren</span><span class="value" style="font-size: 8pt;">' . number_format($bordro->issizlik_isveren ?? 0, 2, ',', '.') . ' ₺</span></td>
                <td class="cost-td" width="40%" style="border-left: 1px solid #e2e8f0;"><span class="day-label" style="color: #2563eb;">Toplam Maliyet</span><span class="value" style="color: #2563eb; font-size: 9pt;">' . number_format($bordro->toplam_maliyet ?? 0, 2, ',', '.') . ' ₺</span></td>
            </tr>
        </table>
    </div>

    <table class="signature-section">
        <tr>
            <td width="50%"><div class="signature-box"><div style="height: 40px;"></div><div class="label" style="width: 100%;">İşveren / Yetkili İmza</div></div></td>
            <td width="50%" align="right"><div class="signature-box"><div style="height: 40px;"></div><div class="label" style="width: 100%;">Personel İmza</div></div></td>
        </tr>
    </table>

    <div class="footer">
        Bu belge sistem tarafından otomatik olarak oluşturulmuştur. | Kurumsal İnsan Kaynakları Yönetimi | ' . ($bordro->hesaplama_tarihi ? date('d.m.Y H:i', strtotime($bordro->hesaplama_tarihi)) : '-') . '
    </div>
</body>
</html>';

$fileName = 'bordro_' . str_replace(' ', '_', $personel->adi_soyadi) . '_' . date('Y_m', strtotime($bordro->baslangic_tarihi)) . '.pdf';

$mpdf = new Mpdf([
    'mode' => 'utf-8',
    'format' => 'A4',
    'margin_left' => 15,
    'margin_right' => 15,
    'margin_top' => 15,
    'margin_bottom' => 15,
]);

$mpdf->SetTitle('Maaş Bordrosu - ' . ($personel->adi_soyadi ?? ''));
$mpdf->WriteHTML($html);
$mpdf->Output($fileName, 'I');
exit;
