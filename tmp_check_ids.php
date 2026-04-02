<?php
require 'bootstrap.php';
$db = (new \App\Model\Model())->db;
$sql = "SELECT id, tur_adi, is_emri_sonucu, rapor_sekmesi, is_turu_ucret 
        FROM tanimlamalar 
        WHERE id BETWEEN 1910 AND 1930";
$res = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
echo "Tanımlamalar (ID 1910-1930):\n";
foreach ($res as $r) {
    echo "ID: " . $r['id'] . " | TİP: '" . $r['tur_adi'] . "' | SONUÇ: '" . $r['is_emri_sonucu'] . "' | SEKME: '" . $r['rapor_sekmesi'] . "' | ÜCRET: " . $r['is_turu_ucret'] . "\n";
}
