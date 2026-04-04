<?php
require 'Autoloader.php';
$db = \App\Helper\Database::getInstance()->getConnection();
$stmt = $db->query("SELECT durum, COUNT(*) as c FROM demirbas GROUP BY durum");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
$stmt = $db->query("SELECT hareket_tipi, COUNT(*) as c FROM demirbas_hareketler GROUP BY hareket_tipi");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
$stmt = $db->query("SELECT kaynak, aciklama FROM demirbas_hareketler LIMIT 10");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
