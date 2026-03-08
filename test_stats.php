<?php
session_start();
$_SESSION['firma_id'] = 1;
require 'vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();
$pdo = new PDO("mysql:host={$_ENV['DB_HOST']};dbname={$_ENV['DB_NAME']};charset=utf8", $_ENV['DB_USER'], $_ENV['DB_PASS']);

$table = 'araclar';
        $sql = $pdo->prepare("
            SELECT 
                SUM(CASE WHEN ikame_mi = 0 THEN 1 ELSE 0 END) as toplam_arac,
                SUM(CASE WHEN aktif_mi = 1 AND ikame_mi = 0 THEN 1 ELSE 0 END) as aktif_arac,
                SUM(CASE WHEN aktif_mi = 0 AND ikame_mi = 0 THEN 1 ELSE 0 END) as pasif_arac,
                SUM(CASE WHEN ikame_mi = 1 AND EXISTS (SELECT 1 FROM arac_servis_kayitlari s WHERE s.ikame_arac_id = {$table}.id AND s.ikame_iade_tarihi IS NULL AND s.silinme_tarihi IS NULL) THEN 1 ELSE 0 END) as ikame_arac
            FROM {$table}
            WHERE firma_id = :firma_id2
            AND silinme_tarihi IS NULL
        ");
        $sql->execute([
            'firma_id2' => $_SESSION['firma_id']
        ]);
        print_r($sql->fetch(PDO::FETCH_OBJ));
