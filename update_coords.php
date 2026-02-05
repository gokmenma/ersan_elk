<?php
require_once __DIR__ . '/Autoloader.php';
use App\Core\Db;

$db = (new Db())->db;

// Kahramanmaraş koordinatları
$lat = 37.5847;
$lng = 36.9371;

// Örnek verileri güncelleyelim
$db->exec("UPDATE personel_hareketleri SET konum_enlem = {$lat} + (RAND()-0.5)*0.1, konum_boylam = {$lng} + (RAND()-0.5)*0.1");

echo "Koordinatlar Kahramanmaras bolgesine guncellendi.\n";
