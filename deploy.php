<?php
$dotenv = dirname(__FILE__) . '/.env';
if (file_exists($dotenv)) {
    foreach (file($dotenv, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            [$k, $v] = explode('=', $line, 2);
            $_ENV[trim($k)] = trim($v, " \t\n\r\0\x0B\"'");
        }
    }
}
$secret = $_ENV['DEPLOY_SECRET'] ?? '';

$signature = 'sha256=' . hash_hmac(
    'sha256',
    file_get_contents('php://input'),
    $secret
);

if (!hash_equals($signature, $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '')) {
    $log = date('Y-m-d H:i:s') . " - Unauthorized access effort. Signature mismatch.\n";
    file_put_contents('deploy_log.txt', $log, FILE_APPEND);
    http_response_code(403);
    exit('Yetkisiz');
}

$whoami = trim(shell_exec('whoami'));
$pwd = trim(shell_exec('pwd'));

$target_dir = '/home/ersantrc/repositories/ersan_elk';
if (!is_dir($target_dir)) {
    $target_dir = __DIR__;
}

$log = date('Y-m-d H:i:s') . " - Deployment started. Target: $target_dir\n";

// Fetch and reset --hard to overcome local changes (often caused by FTP or manual edits)
$cmd = "cd $target_dir && git fetch --all 2>&1 && git reset --hard origin/main 2>&1";
$output = shell_exec($cmd);

$log .= "Command: $cmd\n";
$log .= "Output: $output\n";
$log .= "--------------------------------------------------\n";

file_put_contents('deploy_log.txt', $log, FILE_APPEND);
echo "Deployment Finished. Output logged.";
echo "<pre>$output</pre>";



