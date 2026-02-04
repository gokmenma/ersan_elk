<?php
require_once 'Autoloader.php';
$db = (new \App\Core\Db())->db;
$stmt = $db->query("SELECT * FROM kacak_kontrol WHERE silinme_tarihi IS NULL LIMIT 20");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
echo "\nTotal count: " . $db->query("SELECT count(*) FROM kacak_kontrol WHERE silinme_tarihi IS NULL")->fetchColumn();
