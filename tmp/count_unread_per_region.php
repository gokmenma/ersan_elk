<?php
require_once dirname(__DIR__) . '/bootstrap.php';
$db = (new \App\Model\Model('tanimlamalar'))->getDb();
$sql = "SELECT t.defter_bolge, COUNT(*) as unread_count
        FROM tanimlamalar t
        LEFT JOIN (
            SELECT defter, MAX(tarih) as son_tarih
            FROM endeks_okuma
            WHERE silinme_tarihi IS NULL
            GROUP BY defter
        ) as last_reading ON t.tur_adi = last_reading.defter
        WHERE t.grup = 'defter_kodu' AND t.firma_id = 1 AND t.silinme_tarihi IS NULL
        AND DATEDIFF(CURDATE(), COALESCE(last_reading.son_tarih, t.baslangic_tarihi)) >= 35
        GROUP BY t.defter_bolge
        ORDER BY unread_count DESC";

$stmt = $db->query($sql);
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
