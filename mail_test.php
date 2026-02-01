<?php

require_once 'bootstrap.php';

use App\Service\MailGonderService;
use App\Model\SettingsModel;
use App\Helper\Helper;

$Settings = new SettingsModel();
$firma_id = $_SESSION['firma_id'] ?? 1; // CLI ortamında session boş olabileceği için varsayılan 1
$allSettings = $Settings->getAllSettingsAsKeyValue($firma_id);


echo "=== Mail Ayarları ===\n";
echo "Host: " . ($allSettings['smtp_host'] ?? 'YOK') . "\n";
echo "Port: " . ($allSettings['smtp_port'] ?? 'YOK') . "\n";
echo "Kullanıcı: " . ($allSettings['smtp_kullanici'] ?? 'YOK') . "\n";
echo "Güvenlik: " . ($allSettings['smtp_guvenlik'] ?? 'YOK') . "\n";
echo "Gönderen: " . ($allSettings['gonderen_eposta'] ?? 'YOK') . "\n";
echo "==================\n\n";

$to = "beyzade83@gmail.com";
$subject = "Test Mail - " . date('d.m.Y H:i:s');
$message = "Bu bir test mailidir. Tarih: " . date('d.m.Y H:i:s');

try {
    $result = MailGonderService::gonder([$to], $subject, $message);
    if ($result) {
        echo "Mail başarıyla gönderildi.\n";
    } else {
        echo "Mail gönderilemedi (false döndü).\n";
    }
} catch (\Throwable $e) {
    echo "HATA: " . $e->getMessage() . "\n";
}
