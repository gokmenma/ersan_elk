<?php
require_once __DIR__ . '/../bootstrap.php';
$db = (new App\Model\PersonelModel())->getDb();
$s = $db->query("SELECT id, adi_soyadi, maas_tutari, maas_durumu FROM personel WHERE adi_soyadi LIKE '%Gözübüyük%'");
print_r($s->fetchAll(PDO::FETCH_ASSOC));
