<?php
require_once 'Autoloader.php';
$db = (new App\Model\Model('arac_km_bildirimleri'))->getDb();
foreach(['arac_km_bildirimleri', 'arac_km_kayitlari'] as $table) {
    echo "--- $table ---\n";
    $stmt = $db->query("DESCRIBE $table");
    foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $col) {
        if ($col['Field'] == 'tarih') {
            echo "Field: {$col['Field']}, Type: {$col['Type']}\n";
        }
    }
}
