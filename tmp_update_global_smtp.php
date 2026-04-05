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
    // Global ayarlar (firma_id IS NULL) için güncelle veya ekle
    $stmt = $pdo->prepare("SELECT id FROM settings WHERE set_name = ? AND firma_id IS NULL");
    $stmt->execute([$name]);
    $existing = $stmt->fetchColumn();

    if ($existing) {
        $stmt = $pdo->prepare("UPDATE settings SET set_value = ? WHERE id = ?");
        $stmt->execute([$value, $existing]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO settings (set_name, set_value, firma_id) VALUES (?, ?, NULL)");
        $stmt->execute([$name, $value]);
    }
    echo "Updated Global $name to $value\n";
}
echo "Done.\n";
