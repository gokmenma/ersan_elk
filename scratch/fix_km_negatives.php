<?php
require_once dirname(__DIR__) . '/Autoloader.php';
$db = (new \App\Model\AracKmModel())->getDb();
try {
    // Determine current type (STORED or VIRTUAL) and update
    // We'll try to change it to handled GREATEST(0, bitis_km - baslangic_km) 
    // and also handle the case where bitis_km is 0 (daily progress)
    $sql = "ALTER TABLE arac_km_kayitlari MODIFY COLUMN yapilan_km INT(11) GENERATED ALWAYS AS (IF(bitis_km > 0 AND bitis_km >= baslangic_km, bitis_km - baslangic_km, 0)) STORED";
    $db->exec($sql);
    echo "SUCCESS: Table structure updated.";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
