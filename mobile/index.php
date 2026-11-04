<?php

require_once dirname(__DIR__) . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();

header('Location: ' . \App\Helper\MobileSpa::fromLegacy((string) ($_GET['p'] ?? 'home'), $_GET), true, 302);
exit;
