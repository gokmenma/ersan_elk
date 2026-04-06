<?php
require_once dirname(__DIR__) . '/bootstrap.php';
// Simulating logged in Mehmet Kiraz (ID: 114)
$_SESSION['id'] = 114;
$_SESSION['firma_id'] = 1;

// Part of api.php logic for getDelayedReadings
$personel_id = 114;
$PersonelModel = new \App\Model\PersonelModel();
$TanimlamalarModel = new \App\Model\TanimlamalarModel();

$ekipGecmisi = $PersonelModel->getEkipGecmisi($personel_id);
$sefEkipKoduId = 0;
foreach ($ekipGecmisi as $g) {
    if (($g->ekip_sefi_mi ?? 0) == 1 && (empty($g->bitis_tarihi) || $g->bitis_tarihi >= date('Y-m-d'))) {
        $sefEkipKoduId = $g->ekip_kodu_id;
        break;
    }
}
echo "Sef Ekip Kodu ID: $sefEkipKoduId\n";

$ekipKodu = $TanimlamalarModel->find($sefEkipKoduId);
$bolgeIdari = trim($ekipKodu->ekip_bolge ?? '');
echo "Region: $bolgeIdari\n";

$bolgeUpper = mb_strtoupper($bolgeIdari, 'UTF-8');
$isBeldeler = ($bolgeUpper == 'BELDELER');
echo "Is Beldeler: " . ($isBeldeler ? "Yes" : "No") . "\n";

$db = $TanimlamalarModel->getDb();
$whereClause = "t.grup = 'defter_kodu' AND t.firma_id = 1 AND t.silinme_tarihi IS NULL";
if (!$isBeldeler) {
    $whereClause .= " AND UPPER(t.defter_bolge) = 'BELDELER'"; // Dummy but let's see
}

// Full Query
$sql = "SELECT 
            t.tur_adi as defter_kodu,
            t.defter_mahalle as mahalle,
            t.defter_bolge as bolge,
            COALESCE(last_reading.son_tarih, t.baslangic_tarihi) as son_okuma_tarihi,
            DATEDIFF(CURDATE(), COALESCE(last_reading.son_tarih, t.baslangic_tarihi)) as gun
        FROM tanimlamalar t
        LEFT JOIN (
            SELECT defter, MAX(tarih) as son_tarih
            FROM endeks_okuma
            WHERE silinme_tarihi IS NULL
            GROUP BY defter
        ) as last_reading ON t.tur_adi = last_reading.defter
        WHERE $whereClause
        HAVING gun >= 35
        ORDER BY gun DESC";

$stmt = $db->query($sql);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Count: " . count($results) . "\n";
if (count($results) > 0) {
    echo "First item: " . $results[0]['defter_kodu'] . " - " . $results[0]['gun'] . " days\n";
}
