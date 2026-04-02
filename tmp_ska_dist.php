<?php
require 'bootstrap.php';
$db = (new \App\Model\Model())->db;
$sql = "SELECT tn.id, tn.tur_adi, tn.is_emri_sonucu, tn.rapor_sekmesi, tn.is_turu_ucret, COUNT(*) as record_count 
        FROM yapilan_isler t 
        LEFT JOIN tanimlamalar tn ON t.is_emri_sonucu_id = tn.id 
        WHERE (t.is_emri_sonucu LIKE '%SKA%' OR t.is_emri_sonucu LIKE '%Sayaç Kullanıma açıldı%') 
        AND t.silinme_tarihi IS NULL 
        GROUP BY tn.id";
$res = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
echo "Dağılım:\n";
foreach ($res as $r) {
    echo "ID: " . ($r['id'] ?: 'NULL') . " | TİP: '" . $r['tur_adi'] . "' | SEKME: '" . $r['rapor_sekmesi'] . "' | ÜCRET: " . ($r['is_turu_ucret'] ?: 0) . " | ADET: " . $r['record_count'] . "\n";
}
