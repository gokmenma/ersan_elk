<?php
require 'bootstrap.php';
$db = (new \App\Model\Model())->db;
$sql = "SELECT COUNT(*) FROM kacak_kontrol WHERE firma_id = 1 AND silinme_tarihi IS NULL";
echo "Kacak Kontrol Records: " . $db->query($sql)->fetchColumn() . "\n";

$sql2 = "SELECT COUNT(*) FROM yapilan_isler 
         WHERE firma_id = 1 AND is_emri_tipi LIKE '%KAÇAK%' AND silinme_tarihi IS NULL";
echo "Yapilan Isler (Kacak-ish): " . $db->query($sql2)->fetchColumn() . "\n";
