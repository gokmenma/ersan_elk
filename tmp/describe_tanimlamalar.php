<?php
require_once dirname(__DIR__) . '/bootstrap.php';
use App\Model\Model;

$db = (new Model('tanimlamalar'))->getDb();

$sql = "DESCRIBE tanimlamalar";
$columns = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);

print_r($columns);
