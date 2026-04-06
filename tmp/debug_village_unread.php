<?php
require_once dirname(__DIR__) . '/bootstrap.php';
$db = (new \App\Model\Model('tanimlamalar'))->getDb();
$sql = "SELECT COUNT(*) FROM tanimlamalar t 
        LEFT JOIN (
            SELECT defter, MAX(tarih) as son_tarih 
            FROM endeks_okuma 
            WHERE silinme_tarihi IS NULL 
            GROUP BY defter
        ) as last_reading ON t.tur_adi = last_reading.defter 
        WHERE t.grup = 'defter_kodu' AND t.firma_id = 1 AND t.silinme_tarihi IS NULL 
        AND DATEDIFF(CURDATE(), COALESCE(last_reading.son_tarih, t.baslangic_tarihi)) >= 35 
        AND CAST(t.tur_adi AS UNSIGNED) >= 600";
echo "Total Unread Village Defters (>=600): " . $db->query($sql)->fetchColumn() . "\n";
