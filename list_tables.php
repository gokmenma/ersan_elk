<?php
require_once 'Autoloader.php';
$dbClass = new \App\Core\Db();
$db = $dbClass->getConnection();
$stmt = $db->query("SHOW TABLES");
$tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
foreach ($tables as $table) {
    echo $table . "\n";
}
