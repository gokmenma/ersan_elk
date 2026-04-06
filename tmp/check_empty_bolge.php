<?php
require_once dirname(__DIR__) . '/bootstrap.php';
use App\Model\Model;

$db = (new Model('tanimlamalar'))->getDb();

$sql = "SELECT tur_adi, defter_mahalle, defter_bolge FROM tanimlamalar WHERE grup = 'defter_kodu' AND (defter_bolge IS NULL OR defter_bolge = '') AND silinme_tarihi IS NULL LIMIT 20";
$res = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);

echo "EMPTY BOLGE DEFTERS:\n";
print_r($res);
