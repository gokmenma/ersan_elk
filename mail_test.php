<?php

require_once 'bootstrap.php';

use App\Service\MailGonderService;
use App\Model\SettingsModel;
use App\Helper\Helper;

$Settings = new SettingsModel();
$firma_id = $_SESSION['firma_id'] ?? 1; // CLI ortamında session boş olabileceği için varsayılan 1
$allSettings = $Settings->getAllSettingsAsKeyValue($firma_id);


//print_r($allSettings);

$to = "beyzade83@gmail.com";
$subject = "Gelen posta";
$message = "Bu bir  mailidir.";

if (MailGonderService::gonder([$to], $subject, $message)) {

    echo "Mail başarıyla gönderildi.";
} else {
    echo "Mail gönderilemedi.";
}
