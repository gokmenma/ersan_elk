<?php
require_once __DIR__ . '/Autoloader.php';
use App\Core\Db;
$db = (new Db())->db;
$stmt = $db->query('DESCRIBE personel');
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['Field'] . ' | ' . $row['Type'] . "\n";
}
