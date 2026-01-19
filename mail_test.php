<?php

require_once  'bootstrap.php';

use App\Service\MailGonderService;

$to = "beyzade83@gmail.com";
$subject = "Gelen posta";
$message = "Bu bir  mailidir.";

if (MailGonderService::gonder([$to], $subject, $message)) {

    echo "Mail başarıyla gönderildi.";
} else {
    echo "Mail gönderilemedi.";
}
