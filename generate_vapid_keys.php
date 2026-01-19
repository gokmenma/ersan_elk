<?php
$opensslConf = 'C:\xampp\php\extras\ssl\openssl.cnf';
if (file_exists($opensslConf)) {
    putenv("OPENSSL_CONF=$opensslConf");
} else {
    $opensslConf = 'C:\xampp\apache\conf\openssl.cnf';
    if (file_exists($opensslConf)) {
        putenv("OPENSSL_CONF=$opensslConf");
    }
}

require_once 'vendor/autoload.php';

use Minishlink\WebPush\VAPID;

$keys = VAPID::createVapidKeys();

echo "VAPID_PUBLIC_KEY=" . $keys['publicKey'] . "\n";
echo "VAPID_PRIVATE_KEY=" . $keys['privateKey'] . "\n";
