<?php
// Checks duplicates by set_name in settings table.
// Run: php admin\test\settings_duplicates_probe.php

require_once dirname(__DIR__, 1) . '/Autoloader.php';

use App\Core\Db;

$db = (new Db())->getConnection();

$sql = "SELECT set_name, COUNT(*) AS cnt
        FROM settings
        GROUP BY set_name
        HAVING COUNT(*) > 1
        ORDER BY cnt DESC, set_name ASC";

$stmt = $db->query($sql);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$rows) {
    echo "No duplicates found.\n";
    exit(0);
}

echo "Duplicates found: " . count($rows) . "\n";
foreach ($rows as $r) {
    echo $r['set_name'] . " => " . $r['cnt'] . "\n";
}
