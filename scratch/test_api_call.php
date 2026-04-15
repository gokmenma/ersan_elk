<?php
$url = "http://localhost/ersan_elk/views/arac-takip/api.php?action=get-km-onay-yapmayanlar";
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
// We might need session cookie if it's protected, but let's try
$response = curl_exec($ch);
echo "RESPONSE:\n" . $response . "\n";
curl_close($ch);
