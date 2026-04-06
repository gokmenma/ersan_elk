<?php
require_once dirname(__DIR__) . '/bootstrap.php';
$db = (new \App\Model\Model('tanimlamalar'))->getDb();
$stmt = $db->query("SHOW COLUMNS FROM tanimlamalar");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
