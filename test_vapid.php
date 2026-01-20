<?php
require_once 'Autoloader.php';

$configPath = 'App/Config/vapid.php';
$config = require $configPath;

header('Content-Type: application/json');
echo json_encode([
    'publicKey' => $config['publicKey'],
    'length' => strlen($config['publicKey'])
]);
