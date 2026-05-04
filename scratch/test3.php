<?php
require_once __DIR__ . '/../bootstrap.php';
$db = (new App\Model\BordroPersonelModel())->getDb();
$s = $db->query("SELECT * FROM bordro_personel WHERE personel_id = 77 AND donem_id = 20");
$r = $s->fetch(PDO::FETCH_ASSOC);
print_r($r);
