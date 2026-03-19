<?php
require_once dirname(__DIR__, 2) . '/Autoloader.php';
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Helper\Security;
use App\Helper\Helper;
use App\Model\CariModel;
use Mpdf\Mpdf;

$Cari = new CariModel();
$db = $Cari->getDb();

// Get all movements joined with cari names
$sql = "SELECT h.*, c.CariAdi, c.firma
        FROM cari_hareketleri h
        LEFT JOIN cari c ON h.cari_id = c.id
        WHERE h.silinme_tarihi IS NULL AND c.silinme_tarihi IS NULL
        ORDER BY h.islem_tarihi DESC, h.id DESC";

$stmt = $db->query($sql);
$hareketler = $stmt->fetchAll(PDO::FETCH_OBJ);

// Calculate totals
$toplam_borc = 0;
$toplam_alacak = 0;
foreach($hareketler as $h) {
    $toplam_borc += $h->borc;
    $toplam_alacak += $h->alacak;
}
$genel_bakiye = $toplam_alacak - $toplam_borc;

function fmt($num) {
    return number_format((float)$num, 2, ',', '.');
}

$html = '
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: "Dejavu Sans", sans-serif; font-size: 9px; color: #333; }
        .header { text-align: center; border-bottom: 2px solid #135bec; padding-bottom: 10px; margin-bottom: 20px; }
        .header h1 { color: #135bec; margin: 0; font-size: 16px; }
        .info-table { width: 100%; margin-bottom: 20px; border-collapse: collapse; }
        .info-table td { padding: 5px; vertical-align: top; }
        .movements-table { width: 100%; border-collapse: collapse; }
        .movements-table th { background: #135bec; color: white; padding: 6px; text-align: left; font-size: 8px; }
        .movements-table td { padding: 6px; border-bottom: 1px solid #e2e8f0; }
        .text-right { text-align: right; }
        .footer { position: fixed; bottom: 0; width: 100%; text-align: center; font-size: 8px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 5px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>TÜM CARİ İŞLEMLER LİSTESİ</h1>
        <p>'.date('d.m.Y H:i').' tarihinde oluşturuldu</p>
    </div>

    <table class="info-table">
        <tr>
            <td style="width: 50%">
                <strong>Rapor Kapsamı:</strong> TÜM CARİLER<br>
                <strong>Toplam Kayıt:</strong> '.count($hareketler).' İşlem
            </td>
            <td style="width: 50%; text-align: right;">
                Top. Aldım: '.fmt($toplam_borc).' ₺<br>
                Top. Verdim: '.fmt($toplam_alacak).' ₺<br>
                <strong>Genel Durum: '.fmt(abs($genel_bakiye)).' ₺ '.($genel_bakiye < 0 ? "(Borçluyuz)" : ($genel_bakiye > 0 ? "(Alacaklıyız)" : "")).'</strong>
            </td>
        </tr>
    </table>

    <table class="movements-table">
        <thead>
            <tr>
                <th style="width: 15%">Tarih</th>
                <th style="width: 20%">Cari Adı</th>
                <th style="width: 10%">Belge No</th>
                <th style="width: 25%">Açıklama</th>
                <th style="width: 15%" class="text-right">Borç (Aldım)</th>
                <th style="width: 15%" class="text-right">Alacak (Verdim)</th>
            </tr>
        </thead>
        <tbody>';

foreach ($hareketler as $h) {
    $html .= '
            <tr>
                <td>'.date('d.m.Y H:i', strtotime($h->islem_tarihi)).'</td>
                <td><strong>'.htmlspecialchars($h->CariAdi).'</strong>'.($h->firma ? '<br><small style="color:#666">'.$h->firma.'</small>' : '').'</td>
                <td>'.htmlspecialchars($h->belge_no ?: "-").'</td>
                <td>'.htmlspecialchars($h->aciklama ?: "-").'</td>
                <td class="text-right">'.($h->borc > 0 ? fmt($h->borc) : "-").'</td>
                <td class="text-right">'.($h->alacak > 0 ? fmt($h->alacak) : "-").'</td>
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
    
    $mpdf->SetTitle('Tüm Cari İşlemler');
    $mpdf->WriteHTML($html);
    $mpdf->Output('Tum_Cari_Islemler_' . date('dmY_Hi') . '.pdf', 'D');
} catch (Exception $e) {
    echo "PDF Hatası: " . $e->getMessage();
}
