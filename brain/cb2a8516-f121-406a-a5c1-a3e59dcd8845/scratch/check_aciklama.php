<?php
require_once 'C:/xampp/htdocs/ersan_elk/Autoloader.php';
$db = (new App\Model\DemirbasModel())->db;
$sql = "SHOW COLUMNS FROM demirbas_hareketler LIKE 'aciklama'";
$res = $db->query($sql)->fetch();
if ($res) {
    echo "COLUMN FOUND: " . print_r($res, true) . "\n";
} else {
    echo "COLUMN NOT FOUND\n";
}
