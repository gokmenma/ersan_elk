<?php
session_start();
$_SESSION['firma_id'] = 1;
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = [
    'action' => 'kacak-excel-kaydet',
    'upload_date' => '20.02.2026'
];
$_FILES = [
    'excel_file' => [
        'name' => 'test_kacak.xlsx',
        'type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'tmp_name' => __DIR__ . '/test_kacak.xlsx',
        'error' => UPLOAD_ERR_OK,
        'size' => filesize(__DIR__ . '/test_kacak.xlsx')
    ]
];
require 'views/puantaj/api.php';
