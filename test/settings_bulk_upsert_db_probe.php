<?php
// Verifies upsertMultipleSettings works when settings table is empty.
// Run: php admin\test\settings_bulk_upsert_db_probe.php

require_once dirname(__DIR__, 1) . '/Autoloader.php';

use App\Model\SettingsModel;

$Settings = new SettingsModel();

$settings = [
    'baslik_satir_1' => 'T.C.',
    'baslik_satir_2' => 'TEST KURUM',
    'alt_bilgi_satir_1' => 'Adres',
];

$ok = $Settings->upsertMultipleSettings($settings);

echo $ok ? "BULK UPSERT OK\n" : "BULK UPSERT FAIL\n";

foreach (array_keys($settings) as $k) {
    echo $k . ' => ' . var_export($Settings->getSettings($k), true) . "\n";
}
