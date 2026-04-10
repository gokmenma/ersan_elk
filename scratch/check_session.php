<?php
require_once __DIR__ . '/../Autoloader.php';
session_start();
echo "Session Data:\n";
print_r($_SESSION);
