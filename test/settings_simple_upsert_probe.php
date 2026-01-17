<?php
// Proves simple exists-check upsert doesn't create a new row on second save.
// Run: php admin\test\settings_simple_upsert_probe.php

require_once dirname(__DIR__, 1) . '/Autoloader.php';

use App\Core\Db;
use App\Model\SettingsModel;

$db = (new Db())->getConnection();
$Settings = new SettingsModel();

$key = 'simple_upsert_probe_key';

$countFn = function() use ($db, $key) {
    $stmt = $db->prepare('SELECT COUNT(*) FROM settings WHERE set_name = :k');
    $stmt->execute([':k' => $key]);
    return (int)$stmt->fetchColumn();
};

$cnt1 = $countFn();
$Settings->upsertSetting($key, 'V1 ' . date('H:i:s'));
$cnt2 = $countFn();
$Settings->upsertSetting($key, 'V2 ' . date('H:i:s'));
$cnt3 = $countFn();

echo "Counts: before={$cnt1}, after1={$cnt2}, after2={$cnt3}\n";

echo "Readback: ";
var_dump($Settings->getSettings($key));
