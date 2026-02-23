<?php
require 'vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();
$db = new PDO('mysql:host=' . $_ENV['DB_HOST'] . ';dbname=' . $_ENV['DB_NAME'], $_ENV['DB_USER'], $_ENV['DB_PASS']);
$stmt = $db->query('DESCRIBE duyurular');
$res = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($res as $r)
    echo $r['Field'] . PHP_EOL;
