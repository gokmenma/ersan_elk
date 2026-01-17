<?php
// CLI probe: Evrak şablon kaydet/oku (ekler + kimin_adina*)
// Usage (PowerShell): php admin\test\evrak_sablon_probe.php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Model\EvrakSablonModel;

$Sablon = new EvrakSablonModel();

$baslik = 'PROBE-' . date('Ymd-His');

$payload = [
    'baslik' => $baslik,
    'icerik' => '<p>probe</p>',
    'ekler' => "Ek 1\nEk 2",
    'kimin_adina' => 'Kişi A',
    'kimin_adina_2' => 'Kişi B',
    'kimin_adina_3' => 'Kişi C',
];

$id = $Sablon->upsertByBaslikFull($payload);
$row = $Sablon->findById((int)$id);

$out = [
    'id' => $id,
    'baslik' => $row->baslik ?? null,
    'ekler' => $row->ekler ?? null,
    'kimin_adina' => $row->kimin_adina ?? null,
    'kimin_adina_2' => $row->kimin_adina_2 ?? null,
    'kimin_adina_3' => $row->kimin_adina_3 ?? null,
];

echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
