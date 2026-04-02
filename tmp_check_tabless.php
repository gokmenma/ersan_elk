<?php
require 'bootstrap.php';
$db = (new \App\Model\Model())->db;
$sql = "SELECT COUNT(*) FROM yapilan_isler t 
        LEFT JOIN tanimlamalar tn ON t.is_emri_sonucu_id = tn.id 
        WHERE t.is_emri_tipi NOT IN ('Endeks Okuma', 'Sayaç Değişimi') 
        AND (tn.rapor_sekmesi IS NULL OR tn.rapor_sekmesi = '0') 
        AND t.silinme_tarihi IS NULL";
$cnt = $db->query($sql)->fetchColumn();
echo "Tablara Girmeyen İş Sayısı: " . $cnt . "\n";

$sql2 = "SELECT tn.tur_adi, tn.is_emri_sonucu, COUNT(*) as cnt 
         FROM yapilan_isler t 
         LEFT JOIN tanimlamalar tn ON t.is_emri_sonucu_id = tn.id 
         WHERE t.is_emri_tipi NOT IN ('Endeks Okuma', 'Sayaç Değişimi') 
         AND (tn.rapor_sekmesi IS NULL OR tn.rapor_sekmesi = '0') 
         AND t.silinme_tarihi IS NULL 
         GROUP BY tn.tur_adi, tn.is_emri_sonucu";
$res = $db->query($sql2)->fetchAll(PDO::FETCH_ASSOC);
echo "Tabsız İş Dağılımı:\n";
print_r($res);
