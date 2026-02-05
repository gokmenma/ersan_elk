<?php
require_once __DIR__ . '/Autoloader.php';
use App\Core\Db;
$db = (new Db())->db;
$res = $db->query('SELECT COUNT(*) as count FROM personel_hareketleri WHERE silinme_tarihi IS NULL')->fetch(PDO::FETCH_ASSOC);
echo "Toplam Hareket: " . $res['count'] . "\n";
$last = $db->query('SELECT * FROM personel_hareketleri ORDER BY zaman DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
print_r($last);
