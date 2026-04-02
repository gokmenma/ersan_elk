<?php
require 'bootstrap.php';
$db = (new \App\Model\Model())->db;
$sql = "SELECT DISTINCT is_emri_sonucu_id 
        FROM yapilan_isler t
        WHERE is_emri_sonucu_id > 0 
        AND NOT EXISTS (SELECT 1 FROM tanimlamalar tn WHERE tn.id = t.is_emri_sonucu_id AND tn.silinme_tarihi IS NULL)
        AND t.silinme_tarihi IS NULL";
$res = $db->query($sql)->fetchAll(PDO::FETCH_COLUMN);
echo "Orphaned is_emri_sonucu_id count: " . count($res) . "\n";
if (!empty($res)) {
    echo "First 10 orphaned IDs: " . implode(', ', array_slice($res, 0, 10)) . "\n";
}

$sql2 = "SELECT COUNT(*) FROM yapilan_isler t 
         WHERE is_emri_sonucu_id > 0 
         AND NOT EXISTS (SELECT 1 FROM tanimlamalar tn WHERE tn.id = t.is_emri_sonucu_id AND tn.silinme_tarihi IS NULL)
         AND t.silinme_tarihi IS NULL";
$cnt = $db->query($sql2)->fetchColumn();
echo "Total records with orphaned IDs: " . $cnt . "\n";
