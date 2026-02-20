<?php
session_start();
$_SESSION['firma_id'] = 1;
$_SERVER['REQUEST_METHOD'] = 'GET';
$_GET = [
    'action' => 'get-tab-content',
    'tab' => 'kacak_kontrol',
    'start_date' => '01.02.2026',
    'end_date' => '28.02.2026'
];
require 'views/puantaj/api.php';
