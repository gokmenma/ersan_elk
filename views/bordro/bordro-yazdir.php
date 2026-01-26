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

$kesintilerDetay = $BordroModel->getDonemKesintileriDetay($personel_id, $bordro->donem_id);
$ekOdemelerDetay = $BordroModel->getDonemEkOdemeleriDetay($personel_id, $bordro->donem_id);
$guncelKesinti = $BordroModel->getDonemKesintileri($personel_id, $bordro->donem_id);
$guncelEkOdeme = $BordroModel->getDonemEkOdemeleri($personel_id, $bordro->donem_id);

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

// HTML İçeriği
$html = '
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: "DejaVu Sans", sans-serif; color: #333; font-size: 10pt; line-height: 1.4; }
        .header { border-bottom: 2px solid #2563eb; padding-bottom: 10px; margin-bottom: 20px; }
        .logo-box { width: 150px; }
        .company-name { font-size: 16pt; font-weight: bold; color: #1e293b; }
        .document-title { font-size: 14pt; color: #2563eb; text-align: right; font-weight: bold; }
        
        .section-title { background: #f1f5f9; padding: 5px 10px; font-weight: bold; color: #475569; margin-bottom: 10px; border-left: 4px solid #2563eb; }
        
        .info-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .info-table td { padding: 4px 0; vertical-align: top; }
        .label { color: #64748b; width: 120px; }
        .value { font-weight: bold; color: #1e293b; }
        
        .main-grid { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .main-grid td { width: 50%; vertical-align: top; padding: 0 10px; }
        .main-grid td:first-child { padding-left: 0; border-right: 1px solid #e2e8f0; }
        .main-grid td:last-child { padding-right: 0; }
        
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th { text-align: left; border-bottom: 1px solid #e2e8f0; padding: 8px 0; color: #64748b; font-size: 9pt; }
        .data-table td { padding: 8px 0; border-bottom: 1px dotted #f1f5f9; }
        .amount { text-align: right; font-weight: bold; }
        .negative { color: #dc2626; }
        .positive { color: #16a34a; }
        
        .summary-box { background: #f8fafc; border: 1px solid #e2e8f0; padding: 15px; border-radius: 8px; margin-top: 20px; }
        .net-salary-row { border-top: 2px solid #2563eb; padding-top: 10px; margin-top: 10px; }
        .net-salary-label { font-size: 12pt; font-weight: bold; }
        .net-salary-value { font-size: 16pt; font-weight: bold; color: #16a34a; text-align: right; }
        
        .footer { margin-top: 30px; font-size: 8pt; color: #94a3b8; text-align: center; border-top: 1px solid #f1f5f9; padding-top: 10px; }
        
        .cost-grid { width: 100%; margin-top: 20px; border-collapse: collapse; }
        .cost-grid td { background: #f1f5f9; padding: 10px; border: 1px solid #fff; text-align: center; border-radius: 4px; }
        .cost-label { font-size: 8pt; color: #64748b; display: block; margin-bottom: 4px; }
        .cost-value { font-weight: bold; color: #1e293b; }
    </style>
</head>
<body>
    <table width="100%" class="header">
        <tr>
            <td class="logo-box">
                <img src="' . dirname(__DIR__, 2) . '/assets/images/logo.png" height="40">
            </td>
            <td>
                <div class="company-name">' . ($firma->firma_adi ?? 'ERSAN ELEKTRİK') . '</div>
                <div style="font-size: 9pt; color: #64748b;">Maaş Ödeme Bordrosu</div>
            </td>
            <td class="document-title">
                ' . $donemAdiTr . '
            </td>
        </tr>
    </table>

    <div class="section-title">Personel Bilgileri</div>
    <table class="info-table">
        <tr>
            <td class="label">Ad Soyad:</td>
            <td class="value">' . ($personel->adi_soyadi ?? '-') . '</td>
            <td class="label">TC Kimlik:</td>
            <td class="value">' . ($personel->tc_kimlik_no ?? '-') . '</td>
        </tr>
        <tr>
            <td class="label">Departman:</td>
            <td class="value">' . ($personel->departman ?? '-') . '</td>
            <td class="label">Görev:</td>
            <td class="value">' . ($personel->gorev ?? '-') . '</td>
        </tr>
        <tr>
            <td class="label">İşe Giriş:</td>
            <td class="value">' . ($personel->ise_giris_tarihi ? date('d.m.Y', strtotime($personel->ise_giris_tarihi)) : '-') . '</td>
            <td class="label">Maaş Tipi:</td>
            <td class="value">' . ($personel->maas_durumu ?? 'Brüt') . '</td>
        </tr>
    </table>

    <table class="main-grid">
        <tr>
            <td>
                <div style="font-weight: bold; color: #2563eb; margin-bottom: 10px;">Kazançlar</div>
                <table class="data-table">
                    <tr>
                        <th>Açıklama</th>
                        <th class="amount">Tutar</th>
                    </tr>
                    <tr>
                        <td>Brüt Maaş</td>
                        <td class="amount">' . number_format($bordro->brut_maas ?? 0, 2, ',', '.') . ' ₺</td>
                    </tr>';

if (!empty($ekOdemelerDetay)) {
    foreach ($ekOdemelerDetay as $ek) {
        $html .= '
                            <tr>
                                <td>' . ucfirst($ek->tur) . '</td>
                                <td class="amount positive">+' . number_format($ek->toplam_tutar, 2, ',', '.') . ' ₺</td>
                            </tr>';
    }
}

$html .= '
                    <tr style="border-top: 1px solid #e2e8f0;">
                        <td style="font-weight: bold;">Toplam Kazanç</td>
                        <td class="amount">' . number_format(floatval($bordro->brut_maas) + $toplamEkOdeme, 2, ',', '.') . ' ₺</td>
                    </tr>
                </table>
            </td>
            <td>
                <div style="font-weight: bold; color: #dc2626; margin-bottom: 10px;">Kesintiler</div>
                <table class="data-table">
                    <tr>
                        <th>Açıklama</th>
                        <th class="amount">Tutar</th>
                    </tr>
                    <tr>
                        <td>SGK İşçi Payı (%14)</td>
                        <td class="amount negative">-' . number_format($bordro->sgk_isci ?? 0, 2, ',', '.') . ' ₺</td>
                    </tr>
                    <tr>
                        <td>İşsizlik Sigortası (%1)</td>
                        <td class="amount negative">-' . number_format($bordro->issizlik_isci ?? 0, 2, ',', '.') . ' ₺</td>
                    </tr>
                    <tr>
                        <td>Gelir Vergisi</td>
                        <td class="amount negative">-' . number_format($bordro->gelir_vergisi ?? 0, 2, ',', '.') . ' ₺</td>
                    </tr>
                    <tr>
                        <td>Damga Vergisi</td>
                        <td class="amount negative">-' . number_format($bordro->damga_vergisi ?? 0, 2, ',', '.') . ' ₺</td>
                    </tr>';

if (!empty($kesintilerDetay)) {
    foreach ($kesintilerDetay as $k) {
        $html .= '
                            <tr>
                                <td>' . ucfirst($k->tur) . '</td>
                                <td class="amount negative">-' . number_format($k->toplam_tutar, 2, ',', '.') . ' ₺</td>
                            </tr>';
    }
}

$html .= '
                    <tr style="border-top: 1px solid #e2e8f0;">
                        <td style="font-weight: bold;">Toplam Kesinti</td>
                        <td class="amount negative">-' . number_format($toplamKesinti, 2, ',', '.') . ' ₺</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <div class="summary-box">
        <table width="100%">
            <tr>
                <td width="50%">
                    <div style="font-weight: bold; color: #475569; margin-bottom: 5px;">Ödeme Detayları</div>
                    <div style="font-size: 9pt;">
                        Banka: ' . number_format($bordro->banka_odemesi ?? 0, 2, ',', '.') . ' ₺<br>
                        Sodexo: ' . number_format($bordro->sodexo_odemesi ?? 0, 2, ',', '.') . ' ₺<br>';

$elden = ($bordro->net_maas ?? 0) - ($bordro->banka_odemesi ?? 0) - ($bordro->sodexo_odemesi ?? 0) - ($bordro->diger_odeme ?? 0);
if ($elden > 0) {
    $html .= 'Elden: ' . number_format($elden, 2, ',', '.') . ' ₺<br>';
}

$html .= '
                    </div>
                </td>
                <td width="50%" style="text-align: right; vertical-align: bottom;">
                    <div class="net-salary-label">NET ÖDENECEK</div>
                    <div class="net-salary-value">' . number_format($bordro->net_maas ?? 0, 2, ',', '.') . ' ₺</div>
                </td>
            </tr>
        </table>
    </div>

    <div style="margin-top: 20px;">
        <div style="font-weight: bold; color: #475569; margin-bottom: 10px; font-size: 9pt;">İşveren Maliyet Özeti</div>
        <table class="cost-grid">
            <tr>
                <td>
                    <span class="cost-label">SGK İşveren (%20.5)</span>
                    <span class="cost-value">' . number_format($bordro->sgk_isveren ?? 0, 2, ',', '.') . ' ₺</span>
                </td>
                <td>
                    <span class="cost-label">İşsizlik İşveren (%2)</span>
                    <span class="cost-value">' . number_format($bordro->issizlik_isveren ?? 0, 2, ',', '.') . ' ₺</span>
                </td>
                <td style="background: #e0f2fe;">
                    <span class="cost-label" style="color: #0369a1;">Toplam Maliyet</span>
                    <span class="cost-value" style="color: #0369a1;">' . number_format($bordro->toplam_maliyet ?? 0, 2, ',', '.') . ' ₺</span>
                </td>
            </tr>
        </table>
    </div>

    <div class="footer">
        Bu belge sistem tarafından otomatik olarak oluşturulmuştur. | Hesaplama Tarihi: ' . ($bordro->hesaplama_tarihi ? date('d.m.Y H:i', strtotime($bordro->hesaplama_tarihi)) : '-') . '
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
