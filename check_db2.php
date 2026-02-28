<?php
require 'Autoloader.php';
$db = new App\Model\DemirbasModel();
$stmt = $db->getDb()->query('DESCRIBE demirbas');
$cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($cols as $c) {
    if ($c['Field'] == 'edinme_tarihi' || $c['Field'] == 'edinme_tutari' || $c['Field'] == 'kalan_miktar' || $c['Field'] == 'durum') {
        echo $c['Field'] . ' : ' . $c['Type'] . PHP_EOL;
    }
}
