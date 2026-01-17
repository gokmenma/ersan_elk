<?php
require_once "db.php";

use Database\Db;

$dbInstance = new Db(); // Db sınıfının bir örneğini oluşturuyoruz.
$db = $dbInstance->connect(); // Veritabanı bağlantısını alıyoruz.
session_start(); // Session'ı başlatıyoruz.
// $user_id = $_SESSION['user_id']; // Session'dan user_id'yi alıyoruz.