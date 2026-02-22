<?php
require 'bootstrap.php';
$db = new PDO('mysql:host=' . $_ENV['DB_HOST'] . ';dbname=' . $_ENV['DB_NAME'] . ';charset=utf8', $_ENV['DB_USER'], $_ENV['DB_PASS']);
print_r($db->query('DESCRIBE hakedis_miktarlari')->fetchAll(PDO::FETCH_ASSOC));
print_r($db->query('DESCRIBE hakedis_kalemleri')->fetchAll(PDO::FETCH_ASSOC));
print_r($db->query('DESCRIBE hakedis_donemleri')->fetchAll(PDO::FETCH_ASSOC));
