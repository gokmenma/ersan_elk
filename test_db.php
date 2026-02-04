<?php
require_once 'Autoloader.php';
$db = (new \App\Core\Db())->db;
$res = $db->query("SHOW TABLES");
print_r($res->fetchAll(PDO::FETCH_COLUMN));

$res = $db->query("SELECT * FROM kacak_kontrol");
$data = $res->fetchAll(PDO::FETCH_ASSOC);
echo "Kacak Kontrol Count: " . count($data) . "\n";
print_r($data);
