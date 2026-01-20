<?php
/**
 * Simple VAPID Key Generator using OpenSSL
 */

// Try to find OpenSSL config
$possiblePaths = [
    'C:\\xampp\\php\\extras\\ssl\\openssl.cnf',
    'C:\\xampp\\apache\\conf\\openssl.cnf',
    'C:\\Program Files\\OpenSSL-Win64\\bin\\openssl.cfg',
];

foreach ($possiblePaths as $path) {
    if (file_exists($path)) {
        putenv("OPENSSL_CONF=$path");
        break;
    }
}

// Generate EC key pair
$config = [
    'curve_name' => 'prime256v1',
    'private_key_type' => OPENSSL_KEYTYPE_EC,
];

$key = openssl_pkey_new($config);

if (!$key) {
    die("Failed to generate key: " . openssl_error_string());
}

$details = openssl_pkey_get_details($key);

if (!$details || !isset($details['ec'])) {
    die("Failed to get key details");
}

// Get X and Y coordinates from EC key
$x = $details['ec']['x'];
$y = $details['ec']['y'];
$d = $details['ec']['d'];

// Create uncompressed public key (0x04 + X + Y)
$publicKeyBinary = "\x04" . $x . $y;

// Base64URL encode
$publicKey = rtrim(strtr(base64_encode($publicKeyBinary), '+/', '-_'), '=');
$privateKey = rtrim(strtr(base64_encode($d), '+/', '-_'), '=');

echo "VAPID_PUBLIC_KEY=" . $publicKey . "\n";
echo "VAPID_PRIVATE_KEY=" . $privateKey . "\n";
echo "\n";
echo "Public Key Length: " . strlen($publicKey) . "\n";
echo "Private Key Length: " . strlen($privateKey) . "\n";

// Output PHP config format
echo "\n--- vapid.php config ---\n";
echo "<?php\n\nreturn [\n";
echo "    'publicKey' => '$publicKey',\n";
echo "    'privateKey' => '$privateKey',\n";
echo "    'subject' => 'mailto:info@ersanelektrik.com'\n";
echo "];\n";
