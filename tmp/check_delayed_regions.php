<?php
require_once dirname(__DIR__) . '/bootstrap.php';
use App\Model\Model;

$db = (new Model('tanimlamalar'))->getDb();

$ids = ['782', '69', '13', '3']; // tur_adi values
$placeholders = implode(',', array_fill(0, count($ids), '?'));

$sql = "SELECT id, tur_adi, defter_bolge FROM tanimlamalar WHERE grup = 'defter_kodu' AND tur_adi IN ($placeholders) AND silinme_tarihi IS NULL";
$stmt = $db->prepare($sql);
$stmt->execute($ids);
$res = $stmt->fetchAll(PDO::FETCH_ASSOC);

print_r($res);
