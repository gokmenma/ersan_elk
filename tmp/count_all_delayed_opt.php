<?php
require_once dirname(__DIR__) . '/bootstrap.php';
use App\Model\Model;

$db = (new Model('tanimlamalar'))->getDb();

$bugun = date('Y-m-d');
$firma_id = 1;

// Better query: JOIN to find max(tarih)
$sql = "SELECT t.tur_adi, t.defter_bolge, stats.son_tarih
        FROM tanimlamalar t
        JOIN (
            SELECT defter, MAX(tarih) as son_tarih 
            FROM endeks_okuma 
            WHERE firma_id = :firma_id AND silinme_tarihi IS NULL 
            GROUP BY defter
        ) stats ON t.tur_adi = stats.defter
        WHERE t.grup = 'defter_kodu' 
        AND t.firma_id = :firma_id 
        AND t.silinme_tarihi IS NULL";

$stmt = $db->prepare($sql);
$stmt->execute(['firma_id' => $firma_id]);
$res = $stmt->fetchAll(PDO::FETCH_ASSOC);

$delayed_count = 0;
foreach ($res as $row) {
    if ($row['son_tarih']) {
        $diff = (time() - strtotime($row['son_tarih'])) / (60 * 60 * 24);
        if ($diff > 35) {
            $delayed_count++;
            // echo "DELAYED: [{$row['defter_bolge']}] {$row['tur_adi']} - $diff days\n";
        }
    }
}
echo "TOTAL DELAYED: $delayed_count\n";
