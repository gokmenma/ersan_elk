<?php
require_once dirname(__DIR__) . '/bootstrap.php';
use App\Model\Model;

$db = (new Model('tanimlamalar'))->getDb();

$sql = "SELECT tur_adi, defter_bolge FROM tanimlamalar WHERE grup = 'defter_kodu' AND UPPER(defter_bolge) = 'BELDELER' AND silinme_tarihi IS NULL";
$res = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);

echo "SEARCH RESULTS BELDELER:\n";
print_r($res);
