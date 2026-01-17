<?php
// Direct DB probe (no HTTP) to verify upsert works.
// Run: php admin\test\settings_upsert_db_probe.php

require_once dirname(__DIR__, 1) . '/Autoloader.php';

use App\Model\SettingsModel;

$Settings = new SettingsModel();

$key = 'probe_key';
$val = 'Probe Value ' . date('Y-m-d H:i:s');

$ok = $Settings->upsertSetting($key, $val);

echo $ok ? "UPSERT OK\n" : "UPSERT FAIL\n";

echo "Readback: ";
var_dump($Settings->getSettings($key));
