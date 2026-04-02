<?php
require 'bootstrap.php';
$db = (new \App\Model\Model())->db;
$sql = "SELECT id, tur_adi, is_emri_sonucu, rapor_sekmesi FROM tanimlamalar WHERE (tur_adi LIKE '%ABONESİZ%' OR is_emri_sonucu LIKE '%ABONESİZ%') AND firma_id = 1";
$res = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
print_r($res);
