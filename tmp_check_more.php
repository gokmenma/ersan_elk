<?php
require 'bootstrap.php';
$db = (new \App\Model\Model())->db;
$sql = "SELECT id, tur_adi, is_emri_sonucu, rapor_sekmesi, is_turu_ucret 
        FROM tanimlamalar 
        WHERE id >= 1659 AND firma_id = 1";
$res = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
echo "Tanımlamalar (ID >= 1659, Firma 1):\n";
foreach ($res as $r) {
    echo "ID: " . $r['id'] . " | " . $r['tur_adi'] . " / " . $r['is_emri_sonucu'] . " | SEKME: " . $r['rapor_sekmesi'] . "\n";
}
