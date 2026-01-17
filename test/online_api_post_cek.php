<?php
// Quick CLI POST test for online-api (çek)

// Simulate POST
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = [
    'action' => 'isbank-online-cek',
    'uid' => getenv('ISBANK_UID') ?: '',
    'pwd' => getenv('ISBANK_PWD') ?: '',
    'BeginDate' => getenv('ISBANK_BEGIN') ?: '03.01.2026 00:00:01',
    'EndDate' => getenv('ISBANK_END') ?: '10.01.2026 23:59:59',
];

require __DIR__ . '/../views/gelir-gider/online-api.php';
