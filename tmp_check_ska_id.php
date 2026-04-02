<?php
require 'bootstrap.php';
$db = (new \App\Model\Model())->db;
$sql = "SELECT is_emri_sonucu_id, is_emri_sonucu, COUNT(*) as cnt 
        FROM yapilan_isler 
        WHERE (is_emri_sonucu LIKE '%SKA%' OR is_emri_sonucu LIKE '%Sayaç Kullanıma açıldı%') 
        AND silinme_tarihi IS NULL 
        GROUP BY is_emri_sonucu_id, is_emri_sonucu";
$res = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
echo "Sıralı İşlem Dağılımı:\n";
print_r($res);
