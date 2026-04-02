<?php
require 'bootstrap.php';
$db = (new \App\Model\Model())->db;
$sql = "SELECT COUNT(*) 
        FROM yapilan_isler t
        JOIN tanimlamalar tn ON t.is_emri_sonucu_id = tn.id
        WHERE t.silinme_tarihi IS NULL AND tn.silinme_tarihi IS NOT NULL";
echo "Records pointing to deleted definitions: " . $db->query($sql)->fetchColumn() . "\n";
