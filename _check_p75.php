<?php
require_once __DIR__ . '/Autoloader.php';
$db = new App\Core\Db();
$pdo = $db->db;

echo "=== ALL KESINTILER FOR PERSONEL 75 ===\n";
$stmt = $pdo->prepare("SELECT pk.id, pk.donem_id, bd.donem_adi, pk.icra_id, pk.tutar, pk.durum, pk.aciklama 
                       FROM personel_kesintileri pk 
                       LEFT JOIN bordro_donemi bd ON pk.donem_id = bd.id
                       WHERE pk.personel_id = 75 AND pk.silinme_tarihi IS NULL");
$stmt->execute();
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    print_r($r);
}
