<?php
require_once __DIR__ . '/Autoloader.php';
$db = new App\Core\Db();
$pdo = $db->db;

$id = 75;
echo "\n--- KESINTILER SEARCH ---\n";
// icra_id'si olsun olmasın tüm kesintilere bakıyoruz
$stmt = $pdo->query("SELECT pk.id, pk.icra_id, pk.tutar, pk.tur, pk.durum, pk.aciklama, bd.donem_adi 
                       FROM personel_kesintileri pk 
                       LEFT JOIN bordro_donemi bd ON pk.donem_id = bd.id
                       WHERE pk.personel_id = $id AND pk.silinme_tarihi IS NULL");
$res = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($res as $r) {
    echo "ID: {$r['id']} | Tur: {$r['tur']} | Tutar: {$r['tutar']} | IcraID: " . ($r['icra_id'] ?: 'NULL') . " | Durum: {$r['durum']} | Aciklama: {$r['aciklama']} | Donem: {$r['donem_adi']}\n";
}
