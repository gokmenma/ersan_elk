<?php
require_once __DIR__ . '/Autoloader.php';
$db_class = new \App\Core\Db();
$db = $db_class->getConnection();
$db->exec("ALTER TABLE duyurular ADD COLUMN etkinlik_tarihi DATETIME DEFAULT NULL AFTER tarih");
echo "Column added.";
