<?php
require_once 'Autoloader.php';
use App\Model\UserModel;

$userModel = new UserModel();
$db = $userModel->getDb();

echo "User Notification Settings:\n";
$stmt = $db->query("SELECT id, user_name, adi_soyadi, mail_avans_talep, mail_izin_talep, mail_genel_talep, mail_ariza_talep, izin_onayi_yapacakmi FROM users");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($users as $user) {
    echo "ID: {$user['id']} | Name: {$user['adi_soyadi']} ({$user['user_name']})\n";
    echo "  Avans: {$user['mail_avans_talep']} | Izin: {$user['mail_izin_talep']} | Genel: {$user['mail_genel_talep']} | Ariza: {$user['mail_ariza_talep']} | Izin Onay: {$user['izin_onayi_yapacakmi']}\n";
    echo "--------------------------------------------------\n";
}
