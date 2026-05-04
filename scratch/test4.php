<?php
require_once __DIR__ . '/../bootstrap.php';
$db = (new App\Model\BordroPersonelModel())->getDb();
$s = $db->query("SELECT id, brut_maas, net_maas, prim_tutar, hesaplama_detay FROM bordro_personel WHERE personel_id = 77 AND donem_id = 20");
$r = $s->fetch(PDO::FETCH_ASSOC);
print_r($r);
echo "\n==== HESAPLAMA DETAY ====\n";
print_r(json_decode($r['hesaplama_detay'], true));
