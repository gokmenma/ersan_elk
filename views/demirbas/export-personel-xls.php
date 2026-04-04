<?php
session_start();
require_once dirname(__DIR__, 2) . '/Autoloader.php';

use App\Helper\Security;
use App\Model\DemirbasModel;
use App\Model\TanimlamalarModel;

if (!isset($_SESSION['id'])) {
    die("Yetkisiz erişim!");
}

$Demirbas = new DemirbasModel();
$Tanimlamalar = new TanimlamalarModel();

$personel_id = $_POST['personel_id'] ?? '';
$kategori = $_POST['kategori'] ?? 'sayac';

if (empty($personel_id)) {
    die("Geçersiz personel bilgisi!");
}

try {
    // Kategori ID'lerini belirle
    $sayacKatIds = [];
    $tumKategoriler = $Tanimlamalar->getDemirbasKategorileri();
    foreach ($tumKategoriler as $kat) {
        $katAdiLower = mb_strtolower($kat->tur_adi, 'UTF-8');
        if ($kategori === 'sayac' && (str_contains($katAdiLower, 'sayac') || str_contains($katAdiLower, 'sayaç'))) {
            $sayacKatIds[] = (string) $kat->id;
        } elseif ($kategori === 'aparat' && (str_contains($katAdiLower, 'aparat') || $kat->id == 645)) {
            $sayacKatIds[] = (string) $kat->id;
        }
    }

    if (empty($sayacKatIds)) {
        die("Kategori bulunamadı!");
    }

    $katPlaceholders = implode(',', array_fill(0, count($sayacKatIds), '?'));
    $params = $sayacKatIds;
    $params[] = $_SESSION['firma_id'];
    $params[] = $personel_id;

    // Personelin elindeki (zimmet durum='teslim') olan kayıtları getir
    $sql = $Demirbas->db->prepare("
        SELECT 
            p.adi_soyadi,
            d.demirbas_adi,
            d.marka,
            d.model,
            d.seri_no,
            z.teslim_tarihi,
            z.teslim_miktar,
            k.tur_adi as kategori_adi
        FROM demirbas_zimmet z
        INNER JOIN demirbas d ON z.demirbas_id = d.id
        LEFT JOIN tanimlamalar k ON d.kategori_id = k.id AND k.grup = 'demirbas_kategorisi'
        INNER JOIN personel p ON z.personel_id = p.id
        WHERE d.kategori_id IN ($katPlaceholders) 
        AND d.firma_id = ? 
        AND z.personel_id = ? 
        AND z.durum = 'teslim' 
        AND z.silinme_tarihi IS NULL
        ORDER BY z.teslim_tarihi DESC
    ");
    $sql->execute($params);
    $rows = $sql->fetchAll(PDO::FETCH_ASSOC);

    $personel_adi = !empty($rows) ? $rows[0]['adi_soyadi'] : 'Personel';
    $sanitized_name = preg_replace('/[^a-zA-Z0-9_\-\s]/', '', $personel_adi);
    $filename = $sanitized_name . "_" . $kategori . "_seri_listesi_" . date('Ymd_Hi') . ".xls";

    header("Content-Type: application/vnd.ms-excel; charset=utf-8");
    header("Content-Disposition: attachment; filename=\"$filename\"");
    header("Pragma: no-cache");
    header("Expires: 0");

    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
    echo '<head><meta http-equiv="content-type" content="application/vnd.ms-excel; charset=UTF-8"></head>';
    echo '<body>';
    echo '<table border="1">';
    echo '<tr><th colspan="7" style="background-color:#d9edf7; font-size:16px;">' . htmlspecialchars($personel_adi) . ' - Elinde Kalan '.($kategori=='sayac'?'Sayaç':'Aparat').' Listesi</th></tr>';
    echo '<tr style="background-color:#f5f5f5;">
            <th>Sıra</th>
            <th>Teslim Tarihi</th>
            <th>Kategori</th>
            <th>Demirbaş Adı</th>
            <th>Marka / Model</th>
            <th>Seri No</th>
            <th>Miktar</th>
          </tr>';

    $i = 1;
    $toplam = 0;
    foreach ($rows as $r) {
        $tarih = date('d.m.Y', strtotime($r['teslim_tarihi']));
        $markaModel = trim(($r['marka'] ?? '') . ' ' . ($r['model'] ?? ''));
        echo '<tr>
                <td>' . $i++ . '</td>
                <td>' . $tarih . '</td>
                <td>' . htmlspecialchars($r['kategori_adi'] ?? '-') . '</td>
                <td>' . htmlspecialchars($r['demirbas_adi']) . '</td>
                <td>' . htmlspecialchars($markaModel) . '</td>
                <td style="mso-number-format:\'@\';">' . htmlspecialchars($r['seri_no'] ?? '-') . '</td>
                <td>' . $r['teslim_miktar'] . '</td>
              </tr>';
        $toplam += $r['teslim_miktar'];
    }

    echo '<tr>
            <th colspan="6" style="text-align:right;">GENEL TOPLAM:</th>
            <th><strong>' . $toplam . '</strong></th>
          </tr>';
    
    echo '</table>';
    echo '</body></html>';
    exit;

} catch (Exception $ex) {
    die("Hata: " . $ex->getMessage());
}
