<?php
require_once 'Autoloader.php';
use App\Model\BildirimModel;

$model = new BildirimModel();
$db = $model->getDb();

echo "Recent Notifications:\n";
$stmt = $db->query("SELECT * FROM bildirimler ORDER BY id DESC LIMIT 10");
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($notifications as $n) {
    echo "ID: {$n['id']} | User: {$n['user_id']} | Title: {$n['title']} | Read: {$n['is_read']} | Created: {$n['created_at']}\n";
    echo "  Msg: {$n['message']}\n";
    echo "--------------------------------------------------\n";
}
