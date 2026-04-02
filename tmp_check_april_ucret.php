<?php
require 'bootstrap.php';
$db = (new \App\Model\Model())->db;
$sql = "SELECT (tn.is_turu_ucret > 0) as is_paid, COUNT(*) as cnt, SUM(t.sonuclanmis) as total_units 
        FROM yapilan_isler t
        JOIN tanimlamalar tn ON t.is_emri_sonucu_id = tn.id
        WHERE t.firma_id = 1 AND t.tarih BETWEEN '2026-04-01' AND '2026-04-30' 
        AND tn.rapor_sekmesi = 'kesme' AND t.silinme_tarihi IS NULL
        GROUP BY is_paid";
$res = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
print_r($res);
