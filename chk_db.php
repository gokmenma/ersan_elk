<?php
require 'bootstrap.php';
$db = new PDO('mysql:host=' . $_ENV['DB_HOST'] . ';dbname=' . $_ENV['DB_NAME'] . ';charset=utf8', $_ENV['DB_USER'], $_ENV['DB_PASS']);
try {
    $db->query('ALTER TABLE hakedis_donemleri ADD COLUMN ekstra_parametreler JSON DEFAULT NULL AFTER asgari_ucret_guncel;');
} catch (Exception $e) {
}
print_r($db->query('DESCRIBE hakedis_donemleri')->fetchAll(PDO::FETCH_ASSOC));
