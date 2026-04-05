<?php
require 'Autoloader.php';
use App\Core\Db;
$db = new Db();
$pdo = $db->db;

$res = $pdo->query("SELECT set_name, set_value, firma_id FROM settings WHERE set_name LIKE 'smtp%'")->fetchAll(PDO::FETCH_ASSOC);
echo "SMTP SETTINGS IN DB:\n";
foreach($res as $r) {
    $val = $r['set_value'];
    if (strpos($r['set_name'], 'sifre') !== false) $val = '********';
    echo "Firma: " . ($r['firma_id'] ?? 'NULL') . " | " . $r['set_name'] . " = " . $val . "\n";
}
