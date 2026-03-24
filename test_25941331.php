<?php
require __DIR__.'/Autoloader.php';
$db = (new \App\Core\Db())->db;

// Find demirbas ID for 25941331
$stmt = $db->query("SELECT id, demirbas_adi, miktar, kalan_miktar FROM demirbas WHERE seri_no = '25941331'");
$demirbas = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$demirbas) {
    die("Demirbas 25941331 not found.\n");
}

echo "Demirbas:\n";
print_r($demirbas);

$demirbas_id = $demirbas['id'];

$stmt2 = $db->query("
    SELECT hareket_tipi, SUM(miktar) as total, COUNT(*) as count, GROUP_CONCAT(silinme_tarihi) as sil_tarihleri
    FROM demirbas_hareketler 
    WHERE demirbas_id = $demirbas_id 
    GROUP BY hareket_tipi
");
$hareketler = $stmt2->fetchAll(PDO::FETCH_ASSOC);

echo "\nHareketler:\n";
print_r($hareketler);

// Dynamic Query
$stmt3 = $db->query("
    SELECT (COALESCE(miktar, 1) - COALESCE((SELECT SUM(miktar) FROM demirbas_hareketler WHERE demirbas_id = demirbas.id AND hareket_tipi = 'zimmet' AND silinme_tarihi IS NULL), 0) + COALESCE((SELECT SUM(miktar) FROM demirbas_hareketler WHERE demirbas_id = demirbas.id AND hareket_tipi = 'iade' AND silinme_tarihi IS NULL), 0)) as kalan_miktar_val 
    FROM demirbas WHERE id = $demirbas_id
");
$dyn = $stmt3->fetch(PDO::FETCH_ASSOC);
echo "\nDynamic Calculation: " . $dyn['kalan_miktar_val'] . "\n";
