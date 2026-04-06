<?php
require_once dirname(__DIR__) . '/bootstrap.php';
use App\Model\Model;

$db = (new Model('tanimlamalar'))->getDb();

$sql = "SELECT * FROM tanimlamalar WHERE grup = 'defter_kodu' AND silinme_tarihi IS NULL";
$res = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);

foreach ($res as $row) {
    foreach ($row as $col => $val) {
        if (stripos((string)$val, 'Beldeler') !== false) {
            echo "MATCH in Defter ID {$row['id']}: Column [$col] = $val\n";
        }
    }
}
