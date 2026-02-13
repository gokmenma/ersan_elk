<?php
require_once __DIR__ . '/Autoloader.php';
$db = new App\Core\Db();
$pdo = $db->db;

echo "=== PERSONEL ICRYALARI ===\n";
$stmt = $pdo->query("SELECT id, personel_id, dosya_no, toplam_borc FROM personel_icralari WHERE silinme_tarihi IS NULL");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    print_r($r);
}

echo "\n=== ILE ILGILI KESINTILER ===\n";
$stmt = $pdo->query("SELECT id, icra_id, tutar, durum, aciklama, donem_id FROM personel_kesintileri WHERE icra_id IS NOT NULL AND silinme_tarihi IS NULL");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    print_r($r);
}

echo "\n=== ICRA_ID'SI OLMAYAN AMA ICRA ACIKLAMALI KESINTILER ===\n";
$stmt = $pdo->query("SELECT id, icra_id, tutar, durum, aciklama, donem_id FROM personel_kesintileri WHERE icra_id IS NULL AND aciklama LIKE '%icra%' AND silinme_tarihi IS NULL");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    print_r($r);
}
