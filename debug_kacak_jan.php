<?php
require_once 'Autoloader.php';
$db = (new \App\Core\Db())->db;
$stmt = $db->query("SELECT * FROM kacak_kontrol WHERE tarih LIKE '2026-01%' AND silinme_tarihi IS NULL");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
