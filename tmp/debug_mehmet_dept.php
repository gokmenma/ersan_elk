<?php
require_once dirname(__DIR__) . '/bootstrap.php';
$db = (new \App\Model\Model('personel'))->getDb();
$p = $db->query("SELECT departman FROM personel WHERE id = 114")->fetch(PDO::FETCH_ASSOC);
echo "Department: {$p['departman']}\n";
