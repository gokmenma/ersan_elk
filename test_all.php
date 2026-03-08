<?php
session_start();
$_SESSION['firma_id'] = 1;
require 'vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();
$pdo = new PDO("mysql:host={$_ENV['DB_HOST']};dbname={$_ENV['DB_NAME']};charset=utf8", $_ENV['DB_USER'], $_ENV['DB_PASS']);

$sql = $pdo->prepare("
    SELECT a.*, 
        (SELECT COUNT(*) FROM arac_servis_kayitlari s WHERE s.arac_id = a.id AND s.iade_tarihi IS NULL AND s.silinme_tarihi IS NULL) as serviste_mi
    FROM araclar a
    WHERE a.firma_id = :firma_id 
    AND a.silinme_tarihi IS NULL
    AND (a.ikame_mi = 0 OR (a.ikame_mi = 1 AND EXISTS (SELECT 1 FROM arac_servis_kayitlari s WHERE s.ikame_arac_id = a.id AND s.ikame_iade_tarihi IS NULL AND s.silinme_tarihi IS NULL)))
    ORDER BY a.plaka ASC
");
$sql->execute(['firma_id' => $_SESSION['firma_id']]);
$results = $sql->fetchAll(PDO::FETCH_ASSOC);

$ikames = array_filter($results, function($r) { return $r['ikame_mi'] == 1; });
print_r($ikames);

