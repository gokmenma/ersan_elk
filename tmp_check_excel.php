<?php
require_once 'c:/xampp/htdocs/ersan_elk/vendor/autoload.php';
$appDir = 'c:/xampp/htdocs/ersan_elk/';
require_once $appDir . 'Autoloader.php';

use App\Model\AracYakitModel;
$Yakit = new AracYakitModel();
$db = $Yakit->getDb();

// Fix the 9 deleted records from Feb 2026
$stmt = $db->prepare('
    UPDATE arac_yakit_kayitlari 
    SET silinme_tarihi = NULL
    WHERE YEAR(tarih) = 2026 AND MONTH(tarih) = 2
    AND silinme_tarihi IS NOT NULL
');
$stmt->execute();
echo "Restored rows: " . $stmt->rowCount() . "\n";
