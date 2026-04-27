<?php
require_once 'Autoloader.php';
$db = (new \App\Core\Db())->getConnection();
$res = $db->query("SELECT * FROM settings WHERE name LIKE 'api_endeks%'")->fetchAll(PDO::FETCH_ASSOC);
print_r($res);
