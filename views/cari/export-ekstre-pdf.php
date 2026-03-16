<?php
require_once dirname(__DIR__, 2) . '/Autoloader.php';
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Helper\Security;
use App\Helper\Helper;
use App\Model\CariModel;
use Mpdf\Mpdf;

$cari_id_enc = $_GET['id'] ?? '';
$cari_id = Security::decrypt($cari_id_enc);

$Cari = new CariModel();
$cariData = $Cari->find($cari_id);

if (!$cariData) {
    die("Cari bulunamadı!");
}

$db = $Cari->getDb();

// Özet Bilgiler
$stmt = $db->prepare("SELECT SUM(borc) as toplam_borc, SUM(alacak) as toplam_alacak, SUM(alacak - borc) as bakiye FROM cari_hareketleri WHERE cari_id = :cari_id AND silinme_tarihi IS NULL");
$stmt->execute(['cari_id' => $cari_id]);
$ozet = $stmt->fetch(PDO::FETCH_OBJ);
$toplam_borc = $ozet->toplam_borc ?? 0;
$toplam_alacak = $ozet->toplam_alacak ?? 0;
$bakiye = $ozet->bakiye ?? 0;

// Hareketler
$sql = "SELECT h.*, 
        (SELECT ROUND(SUM(alacak - borc), 2) FROM cari_hareketleri 
         WHERE cari_id = :cari_id AND silinme_tarihi IS NULL 
           AND (islem_tarihi < h.islem_tarihi OR (islem_tarihi = h.islem_tarihi AND id <= h.id))) as yuruyen_bakiye
        FROM cari_hareketleri h
        WHERE h.cari_id = :cari_id AND h.silinme_tarihi IS NULL
        ORDER BY h.islem_tarihi DESC, h.id DESC";

$stmt = $db->prepare($sql);
$stmt->execute(['cari_id' => $cari_id]);
$hareketler = $stmt->fetchAll(PDO::FETCH_OBJ);

function fmt($num) {
    return number_format((float)$num, 2, ',', '.');
}

$html = '
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: "Dejavu Sans", sans-serif; font-size: 10px; color: #333; }
        .header { text-align: center; border-bottom: 2px solid #135bec; padding-bottom: 10px; margin-bottom: 20px; }
        .header h1 { color: #135bec; margin: 0; font-size: 18px; }
        .info-table { width: 100%; margin-bottom: 20px; border-collapse: collapse; }
        .info-table td { padding: 5px; vertical-align: top; }
        .summary-box { background: #f8fafc; border: 1px solid #e2e8f0; padding: 10px; margin-bottom: 20px; border-radius: 5px; }
        .summary-table { width: 100%; }
        .summary-table td { font-weight: bold; }
        .movements-table { width: 100%; border-collapse: collapse; }
        .movements-table th { background: #135bec; color: white; padding: 8px; text-align: left; }
        .movements-table td { padding: 8px; border-bottom: 1px solid #e2e8f0; }
        .text-right { text-align: right; }
        .text-danger { color: #e11d48; }
        .text-success { color: #10b981; }
        .footer { position: fixed; bottom: 0; width: 100%; text-align: center; font-size: 8px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 5px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>HESAP EKSTRESİ</h1>
        <p>'.date('d.m.Y H:i').' tarihinde oluşturuldu</p>
    </div>

    <table class="info-table">
        <tr>
            <td style="width: 60%">
                <strong>Müşteri Bilgileri:</strong><br>
                '.htmlspecialchars($cariData->CariAdi).'<br>
                Tel: '.htmlspecialchars($cariData->Telefon ?: '-').'<br>
                Adres: '.nl2br(htmlspecialchars($cariData->Adres ?: '-')).'
            </td>
            <td style="width: 40%; text-align: right;">
                <strong>Durum:</strong><br>
                Toplam Borç: '.fmt($toplam_borc).' ₺<br>
                Toplam Alacak: '.fmt($toplam_alacak).' ₺<br>
                <span style="font-size: 14px; font-weight: bold;">Cari Bakiye: '.fmt(abs($bakiye)).' ₺ '.($bakiye < 0 ? "(B)" : ($bakiye > 0 ? "(A)" : "")).'</span>
            </td>
        </tr>
    </table>';

if ($cariData->notlar) {
    $html .= '<div class="summary-box" style="background: #fffbeb; border-color: #fef3c7;">
                <strong>Cari Notu:</strong><br>
                '.nl2br(htmlspecialchars($cariData->notlar)).'
              </div>';
}

$html .= '
    <table class="movements-table">
        <thead>
            <tr>
                <th>Tarih</th>
                <th>Belge No</th>
                <th>Açıklama</th>
                <th class="text-right">Borç</th>
                <th class="text-right">Alacak</th>
                <th class="text-right">Bakiye</th>
            </tr>
        </thead>
        <tbody>';

foreach ($hareketler as $h) {
    $currBakiye = $h->yuruyen_bakiye;
    $html .= '
            <tr>
                <td>'.date('d.m.Y H:i', strtotime($h->islem_tarihi)).'</td>
                <td>'.htmlspecialchars($h->belge_no ?: "-").'</td>
                <td>'.htmlspecialchars($h->aciklama ?: "-").'</td>
                <td class="text-right">'.($h->borc > 0 ? fmt($h->borc) : "-").'</td>
                <td class="text-right">'.($h->alacak > 0 ? fmt($h->alacak) : "-").'</td>
                <td class="text-right" style="font-weight: bold;">'.fmt(abs($currBakiye)).' '.($currBakiye < 0 ? "(B)" : ($currBakiye > 0 ? "(A)" : "")).'</td>
            </tr>';
}

if (empty($hareketler)) {
    $html .= '<tr><td colspan="6" style="text-align: center; padding: 20px;">Hareket bulunmamaktadır.</td></tr>';
}

$html .= '
        </tbody>
    </table>

    <div class="footer">
        Ersan Elektrik Cari Yönetim Sistemi - '.date('Y').' | Sayfa: {PAGENO}/{nbpg}
    </div>
</body>
</html>';

try {
    $mpdf = new Mpdf([
        'mode' => 'utf-8',
        'format' => 'A4',
        'margin_left' => 10,
        'margin_right' => 10,
        'margin_top' => 10,
        'margin_bottom' => 15,
    ]);
    
    $mpdf->SetTitle('Hesap Ekstresi - ' . $cariData->CariAdi);
    $mpdf->WriteHTML($html);
    $mpdf->Output('Ekstre_' . str_replace(' ', '_', $cariData->CariAdi) . '_' . date('dmY') . '.pdf', 'D');
} catch (Exception $e) {
    echo "PDF Hatası: " . $e->getMessage();
}
