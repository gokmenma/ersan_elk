<?php
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST['action'] = 'list';
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['id'] = 1;
$_SESSION['firma_id'] = 1;
require __DIR__ . '/api/formlar/islem.php';
