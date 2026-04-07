<?php
require_once 'Autoloader.php';
$db = (new \App\Core\Db())->getConnection();

$tables = ['yapilan_isler', 'endeks_okuma', 'sayac_degisim'];
foreach ($tables as $table) {
    echo "Table: $table\n";
    try {
        $cols = $db->query("DESCRIBE $table")->fetchAll(PDO::FETCH_COLUMN);
        echo "Cols: " . implode(", ", $cols) . "\n";
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
    echo "-------------------\n";
}
