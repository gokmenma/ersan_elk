<?php
require_once 'Autoloader.php';
$db = new \App\Core\Db();
$tables = ['personel', 'araclar', 'arac_zimmetleri', 'arac_km_bildirimleri'];
foreach($tables as $t) {
    echo "\nTABLE: $t\n";
    $stmt = $db->db->prepare("DESCRIBE $t");
    $stmt->execute();
    $cols = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'Field');
    echo implode(", ", $cols) . "\n";
}
