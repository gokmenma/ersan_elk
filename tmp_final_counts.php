<?php
require 'bootstrap.php';
$db = (new \App\Model\Model())->db;
$sql = "SELECT COUNT(*) as total_records 
        FROM yapilan_isler 
        WHERE firma_id = 1 AND silinme_tarihi IS NULL";
echo "Total Records (Firma 1): " . $db->query($sql)->fetchColumn() . "\n";

$sql2 = "SELECT COUNT(*) as total_okuma_sayac 
         FROM yapilan_isler 
         WHERE firma_id = 1 AND silinme_tarihi IS NULL 
         AND is_emri_tipi IN ('Endeks Okuma', 'Sayaç Değişimi')";
echo "Okuma/Sayaç Değişimi (Firma 1): " . $db->query($sql2)->fetchColumn() . "\n";

$sql3 = "SELECT COUNT(*) as total_remaining 
         FROM yapilan_isler 
         WHERE firma_id = 1 AND silinme_tarihi IS NULL 
         AND is_emri_tipi NOT IN ('Endeks Okuma', 'Sayaç Değişimi')";
echo "Remaining (Kesme/Açma etc) (Firma 1): " . $db->query($sql3)->fetchColumn() . "\n";

$sql4 = "SELECT COUNT(*) as in_tabs 
         FROM yapilan_isler t
         JOIN tanimlamalar tn ON t.is_emri_sonucu_id = tn.id
         WHERE t.firma_id = 1 AND t.silinme_tarihi IS NULL 
         AND t.is_emri_tipi NOT IN ('Endeks Okuma', 'Sayaç Değişimi')
         AND tn.rapor_sekmesi IS NOT NULL AND tn.rapor_sekmesi != '0' AND tn.silinme_tarihi IS NULL";
echo "Categorized in Tabs (Firma 1): " . $db->query($sql4)->fetchColumn() . "\n";
