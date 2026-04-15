<?php
require_once 'Autoloader.php';
$db = new \App\Core\Db();
$st = $db->db->prepare('DESCRIBE arac_zimmetleri');
$st->execute();
print_r($st->fetchAll(PDO::FETCH_ASSOC));
