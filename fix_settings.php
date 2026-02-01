<?php
require_once 'bootstrap.php';

$db = (new App\Core\Db())->getConnection();
$stmt = $db->prepare("UPDATE settings SET set_value = ? WHERE set_name = ? AND firma_id = ?");
$stmt->execute(['ssl', 'smtp_guvenlik', 1]);
echo "Güvenlik SSL olarak güncellendi.\n";
