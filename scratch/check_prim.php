<?php
require_once __DIR__ . '/../bootstrap.php';
$db = (new App\Model\PersonelModel())->getDb();
$s = $db->query("SELECT id, adi_soyadi, maas_tutari, maas_durumu FROM personel WHERE maas_durumu LIKE '%Prim%' AND maas_tutari > 0");
print_r($s->fetchAll(PDO::FETCH_ASSOC));
