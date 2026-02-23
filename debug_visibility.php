<?php
require_once 'Autoloader.php';
$db = (new \App\Model\Model('duyurular'))->getDb();
$res = $db->query("SELECT * FROM duyurular")->fetchAll(PDO::FETCH_ASSOC);
echo "TABLE DATA:\n";
echo json_encode($res, JSON_PRETTY_PRINT);
echo "\n\nSESSION DATA:\n";
session_start();
echo json_encode($_SESSION, JSON_PRETTY_PRINT);
