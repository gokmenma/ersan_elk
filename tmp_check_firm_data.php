<?php
require 'bootstrap.php';
$db = (new \App\Model\Model())->db;
$sql = "SELECT firma_id, COUNT(*) as cnt 
        FROM yapilan_isler 
        WHERE silinme_tarihi IS NULL 
        GROUP BY firma_id";
$res = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
echo "Yapılan İşler Firma Bazlı Dağılım:\n";
print_r($res);
