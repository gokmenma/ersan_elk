<?php
require_once 'Autoloader.php';
$db = (new \App\Core\Db())->db;
$stmt = $db->query("SELECT * FROM yapilan_isler WHERE tarih LIKE '2026-01%' AND silinme_tarihi IS NULL LIMIT 20");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
