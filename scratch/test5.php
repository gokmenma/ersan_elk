<?php
require_once __DIR__ . '/../bootstrap.php';
$db = (new App\Model\BordroPersonelModel())->getDb();
$s = $db->query("SELECT id, hesaplama_detay FROM bordro_personel WHERE personel_id = 77 AND donem_id = 20");
$r = $s->fetch(PDO::FETCH_ASSOC);
$detay = json_decode($r['hesaplama_detay'], true);
foreach ($detay['ek_odemeler'] as $ek) {
    echo ($ek['kod'] ?? $ek['tur'] ?? '') . ' -> ' . ($ek['aciklama'] ?? '') . ' (' . ($ek['tutar'] ?? '') . ")\n";
}
