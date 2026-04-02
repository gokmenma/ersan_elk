<?php
require 'bootstrap.php';
$db = (new \App\Model\Model())->db;
$sql = "SELECT id, firma_id, tur_adi, is_emri_sonucu, rapor_sekmesi FROM tanimlamalar WHERE id IN (331, 629)";
$res = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
print_r($res);
