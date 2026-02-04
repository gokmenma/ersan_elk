<?php
require_once 'Autoloader.php';
$db = (new \App\Core\Db())->db;
echo 'Kacak: ' . $db->query('SELECT count(*) FROM kacak_kontrol')->fetchColumn() . "\n";
echo 'Endeks: ' . $db->query('SELECT count(*) FROM endeks_okuma')->fetchColumn() . "\n";
echo 'Yapilan Isler: ' . $db->query('SELECT count(*) FROM yapilan_isler')->fetchColumn() . "\n";
echo 'Tanimlamalar: ' . $db->query('SELECT count(*) FROM tanimlamalar')->fetchColumn() . "\n";
