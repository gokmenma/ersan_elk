<?php
require 'bootstrap.php';
$db = (new \App\Model\Model())->db;
$sql = "SELECT id, tur_adi, is_emri_sonucu, rapor_sekmesi, is_turu_ucret 
        FROM tanimlamalar 
        WHERE (is_emri_sonucu LIKE '%SKA%' OR is_emri_sonucu LIKE '%Sayaç Kullanıma açıldı%') 
        AND silinme_tarihi IS NULL";
$res = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
echo "Tanımlamalar Listesi:\n";
foreach ($res as $r) {
    echo "ID: " . $r['id'] . " | TİP: '" . $r['tur_adi'] . "' | SONUÇ: '" . $r['is_emri_sonucu'] . "' | SEKME: '" . $r['rapor_sekmesi'] . "' | ÜCRET: " . $r['is_turu_ucret'] . "\n";
}
