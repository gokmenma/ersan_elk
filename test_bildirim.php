<?php
require_once 'Autoloader.php';
use App\Model\BildirimModel;
use App\Model\UserModel;

$userId = 1; // Mehmet Ali Gökmen
$title = "Test Bildirimi " . date('H:i:s');
$message = "Bu bir test bildirimidir.";

try {
    $model = new BildirimModel();
    $res = $model->createNotification($userId, $title, $message, 'index.php', 'bell', 'primary');
    echo "Notification created! ID: $res\n";

    $unread = $model->getUnreadNotifications($userId);
    echo "Unread count for user $userId: " . count($unread) . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
