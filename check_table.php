<?php
require 'Autoloader.php';

use App\Core\Db;

$db = (new Db())->db;
$r = $db->query('DESCRIBE bordro_genel_ayarlar');
foreach ($r as $row) {
    echo $row['Field'] . ' - ' . $row['Type'] . "\n";
}
