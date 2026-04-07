<?php
$db=new PDO('mysql:host=localhost;dbname=ersantrc_personel;charset=utf8', 'root', '');
$q = $db->query("SELECT id, durum, kaskiye_teslim_tarihi FROM demirbas WHERE durum = 'Kaskiye Teslim Edildi' LIMIT 10");
$rows = $q->fetchAll();
print_r($rows);
