<?php
require 'Autoloader.php';
use App\Core\Db;
$db = new Db();
$pdo = $db->db;

$settings = [
    'smtp_host' => 'mail.ersantr.com',
    'smtp_port' => '465',
    'smtp_kullanici' => 'noreply@ersantr.com',
    'smtp_sifre' => 'numQAqcWL&1XwAwj',
    'smtp_guvenlik' => 'ssl',
    'gonderen_eposta' => 'noreply@ersantr.com',
    'email_gonderim_aktif' => '1'
];

foreach ($settings as $name => $value) {
    // Firma 1 için güncelle veya ekle
    $stmt = $pdo->prepare("INSERT INTO settings (set_name, set_value, firma_id) VALUES (?, ?, 1) 
                           ON DUPLICATE KEY UPDATE set_value = ?");
    $stmt->execute([$name, $value, $value]);
    echo "Updated $name to $value (firma_id=1)\n";
}

echo "Done.\n";
