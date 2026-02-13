<?php
require_once __DIR__ . '/Autoloader.php';
$db = new App\Core\Db();
$pdo = $db->db;

$id = 75;
$res = $pdo->query("SELECT ad, soyad FROM personel WHERE id = $id")->fetch(PDO::FETCH_ASSOC);
echo "Personel ($id): " . $res['ad'] . " " . $res['soyad'] . "\n";

echo "\n--- ICRA RECORDS ---\n";
$stmt = $pdo->query("SELECT id, dosya_no, icra_dairesi, toplam_borc, aylik_kesinti_tutari, sira FROM personel_icralari WHERE personel_id = $id AND silinme_tarihi IS NULL");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

echo "\n--- KESINTI RECORDS (icra_id IS NOT NULL) ---\n";
$stmt = $pdo->query("SELECT pk.id, pk.icra_id, pk.tutar, pk.durum, pk.aciklama, bd.donem_adi 
                       FROM personel_kesintileri pk 
                       LEFT JOIN bordro_donemi bd ON pk.donem_id = bd.id
                       WHERE pk.personel_id = $id AND pk.icra_id IS NOT NULL AND pk.silinme_tarihi IS NULL");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
