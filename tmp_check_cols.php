<?php
require 'Autoloader.php';
$db = new \App\Core\Db();
$tables = ['personel_avanslari', 'personel_izinleri', 'personel_talepleri'];
foreach($tables as $t) {
    echo "Table: $t\n";
    $stmt = $db->db->query("DESCRIBE $t");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach($columns as $c) {
        echo $c['Field'] . "\n";
    }
    echo "-----\n";
}
