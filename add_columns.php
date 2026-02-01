<?php
try {
    $p = new PDO('mysql:host=localhost;dbname=ersan_personel', 'root', '');
    $p->exec('ALTER TABLE yapilan_isler ADD COLUMN silinme_tarihi DATETIME NULL DEFAULT NULL');
    $p->exec('ALTER TABLE endeks_okuma ADD COLUMN silinme_tarihi DATETIME NULL DEFAULT NULL');
    echo "COLUMNS_ADDED\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
