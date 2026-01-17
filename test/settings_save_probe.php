<?php
// Quick probe to test settings save endpoint without browser
// Run: php admin\test\settings_save_probe.php

$endpoint = 'http://localhost/cansen/admin/views/ayarlar/api.php';

$post = [
    'action' => 'save',
    'settings' => [
        // Replace with an existing set_name from your `settings` table
        'site_title' => 'Probe Test ' . date('Y-m-d H:i:s'),
    ],
];

$ch = curl_init($endpoint);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query($post),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/x-www-form-urlencoded',
    ],
]);

$res = curl_exec($ch);
$err = curl_error($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($res === false) {
    echo "Request failed: {$err}\n";
    exit(1);
}

echo "HTTP {$code}\n";
echo $res . "\n";
