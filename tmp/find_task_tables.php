<?php
require_once 'Autoloader.php';
$dbClass = new \App\Core\Db();
$db = $dbClass->getConnection();

$tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
$found = [];

foreach ($tables as $table) {
    try {
        $cols = $db->query("DESCRIBE $table")->fetchAll(PDO::FETCH_COLUMN);
        if (in_array('personel_id', $cols) && in_array('ekip_kodu_id', $cols)) {
            $found[] = $table;
        }
    } catch (Exception $e) {}
}

echo implode("\n", $found);
