<?php
require 'bootstrap.php';
$db = (new \App\Model\Model())->db;
$sql = "SELECT is_emri_tipi, is_emri_sonucu, COUNT(*) as cnt 
        FROM yapilan_isler 
        WHERE (is_emri_sonucu LIKE '%SKA%' OR is_emri_sonucu LIKE '%Sayaç Kullanıma Açıldı%') 
        AND silinme_tarihi IS NULL 
        GROUP BY is_emri_tipi, is_emri_sonucu";
$res = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
echo "Yapılan İşler (SKA Counts):\n";
print_r($res);

$sqlT = "SELECT tur_adi, is_emri_sonucu, rapor_sekmesi, is_turu_ucret 
         FROM tanimlamalar 
         WHERE (is_emri_sonucu LIKE '%SKA%' OR is_emri_sonucu LIKE '%Sayaç Kullanıma Açıldı%') 
         AND silinme_tarihi IS NULL";
$resT = $db->query($sqlT)->fetchAll(PDO::FETCH_ASSOC);
echo "\nTanımlamalar (SKA Definitions):\n";
print_r($resT);
