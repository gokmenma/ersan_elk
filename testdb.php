<?php
require_once __DIR__ . '/Autoloader.php';
$db = (new \App\Core\Db())->getConnection();
$stmt = $db->query('DESCRIBE personel_giris_loglari');
print_r($stmt->fetchAll(\PDO::FETCH_ASSOC));
