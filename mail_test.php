<?php

require_once  'bootstrap.php';

use App\Service\MailGonderService;
use App\Model\SettingsModel;
use App\Helper\Helper;

$Settings = new SettingsModel();
$allSettings = $Settings->getAllSettingsAsKeyValue();


Helper::dd($allSettings);

// $to = "beyzade83@gmail.com";
// $subject = "Gelen posta";
// $message = "Bu bir  mailidir.";

// if (MailGonderService::gonder([$to], $subject, $message)) {

//     echo "Mail başarıyla gönderildi.";
// } else {
//     echo "Mail gönderilemedi.";
// }
