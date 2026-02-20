<?php
session_start();
$_SESSION['firma_id'] = 1;
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = [
    'action' => 'kacak-kaydet',
    'id' => '0',
    'tarih' => '20.02.2026',
    'kacak_personel_ids' => ['94'],
    'sayi' => '5',
    'aciklama' => 'test'
];
require 'views/puantaj/api.php';
