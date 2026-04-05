<?php
$secret = 'a9f3cE7QmL2R6XKZ8N4VwH0JYbP5DStU';

$signature = 'sha256=' . hash_hmac(
    'sha256',
    file_get_contents('php://input'),
    $secret
);

if (!hash_equals($signature, $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '')) {
    http_response_code(403);
    exit('Yetkisiz');
}

$output = shell_exec('cd /home/ersantrc/public_html/ersantr.com/app && git pull 2>&1');
echo "<pre>$output</pre>";



