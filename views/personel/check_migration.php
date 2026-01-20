<?php
/**
 * Migration Kontrol Script
 */

require_once dirname(__DIR__, 2) . '/Autoloader.php';

use App\Core\Db;

$db = new Db();
$pdo = $db->db;

echo "=== PERSONEL_KESINTILERI TABLO YAPISI ===\n";
$stmt = $pdo->query("DESCRIBE personel_kesintileri");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    echo sprintf("%-20s %-40s %s\n", $row['Field'], $row['Type'], $row['Null']);
}

echo "\n=== PERSONEL_EK_ODEMELER TABLO YAPISI ===\n";
$stmt = $pdo->query("DESCRIBE personel_ek_odemeler");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    echo sprintf("%-20s %-40s %s\n", $row['Field'], $row['Type'], $row['Null']);
}

echo "\n=== BES PARAMETRESI ===\n";
$stmt = $pdo->query("SELECT * FROM bordro_parametreleri WHERE kod = 'bes'");
$bes = $stmt->fetch(PDO::FETCH_ASSOC);
if ($bes) {
    print_r($bes);
} else {
    echo "BES parametresi bulunamadı!\n";
}

echo "\n=== TAMAMLANDI ===\n";
