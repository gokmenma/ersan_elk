<?php
require 'bootstrap.php';
$db = (new \App\Model\Model())->db;
$sql = "SELECT t.is_emri_tipi, t.is_emri_sonucu, COUNT(*) as cnt 
        FROM yapilan_isler t 
        LEFT JOIN tanimlamalar tn ON t.is_emri_sonucu_id = tn.id 
        WHERE t.firma_id = 1 AND t.silinme_tarihi IS NULL 
        AND t.is_emri_tipi NOT IN ('Endeks Okuma', 'Sayaç Değişimi')
        AND (tn.rapor_sekmesi IS NULL OR tn.rapor_sekmesi = '0' OR tn.id IS NULL)
        GROUP BY t.is_emri_tipi, t.is_emri_sonucu
        ORDER BY cnt DESC";
$res = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
echo "Missing Records Distribution (Firma 1):\n";
foreach ($res as $r) {
    echo $r['cnt'] . " x '" . $r['is_emri_tipi'] . "' / '" . $r['is_emri_sonucu'] . "'\n";
}
