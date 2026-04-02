<?php
require 'bootstrap.php';
$db = (new \App\Model\Model())->db;
$sql = "SELECT is_emri_sonucu_id, firma_id, COUNT(*) as cnt 
        FROM yapilan_isler 
        WHERE is_emri_sonucu_id IN (331, 629) 
        GROUP BY is_emri_sonucu_id, firma_id";
$res = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
print_r($res);
