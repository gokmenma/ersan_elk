<?php
session_start();
$_SESSION['id'] = 1;
$_SESSION['firma_id'] = 1;
$_POST['action'] = 'sayac-personel-listesi';
$_POST['kategori'] = 'sayac';
require 'C:\xampp\htdocs\ersan_elk\views\demirbas\api.php';
