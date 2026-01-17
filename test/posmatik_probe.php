<?php

$endpoint = 'https://posmatik2.isbank.com.tr/AuthenticateSpecific.aspx';

$uid = getenv('POSMATIK_UID') ?: 'U53751444N';
$pwd = getenv('POSMATIK_PWD') ?: 'X';
$begin = getenv('POSMATIK_BEGIN') ?: '20.11.2025 00:00:01';
$end = getenv('POSMATIK_END') ?: '01.12.2025 23:59:59';

$post = [
    'uid' => $uid,
    'pwd' => $pwd,
    'BeginDate' => $begin,
    'EndDate' => $end,
];

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $endpoint,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query($post, '', '&', PHP_QUERY_RFC3986),
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/x-www-form-urlencoded',
        'Accept: application/xml,text/xml,*/*',
    ],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT => 60,
    CURLOPT_CONNECTTIMEOUT => 15,
]);

$body = curl_exec($ch);
$err = curl_error($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP=$code\n";
if ($err) {
    echo "ERR=$err\n";
}

$body = (string)$body;
echo "LEN=" . strlen($body) . "\n";
echo substr($body, 0, 800) . "\n";
