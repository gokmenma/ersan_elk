<?php
require_once 'C:/xampp/htdocs/ersan_elk/Autoloader.php';
$db = (new App\Model\DemirbasModel())->db;
$sql = "DESCRIBE demirbas_hareketler";
$rows = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
print_r($rows);
