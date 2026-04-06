<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

$start = microtime(true);
ob_start();
session_start();
$_GET['p'] = 'home';
$_SESSION['user_id'] = 1;
$_SESSION['firma_id'] = 1;

require 'index.php';
ob_end_clean();

$end = microtime(true);
echo "Dashboard Load Time: " . ($end - $start) . " seconds\n";
