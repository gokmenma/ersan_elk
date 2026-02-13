<?php
require_once __DIR__ . '/Autoloader.php';
$db = new App\Core\Db();
$pdo = $db->db;

try {
    $pdo->exec("ALTER TABLE personel_icralari ADD COLUMN sira int(11) DEFAULT 1 AFTER personel_id");
    echo "Column 'sira' added successfully.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
