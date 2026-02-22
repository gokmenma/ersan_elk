<?php
require_once __DIR__ . '/bootstrap.php';
$db = new PDO("mysql:host=" . $_ENV['DB_HOST'] . ";dbname=" . $_ENV['DB_NAME'] . ";charset=utf8", $_ENV['DB_USER'], $_ENV['DB_PASS']);
$stmt = $db->query("DESCRIBE menus");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
