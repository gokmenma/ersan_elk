<?php
require_once dirname(__DIR__) . '/bootstrap.php';
$db = (new \App\Model\Model('tanimlamalar'))->getDb();
$districts = ['AFŞİN', 'ANDIRIN', 'ELBİSTAN', 'GÖKSUN', 'PAZARCIK', 'TÜRKOĞLU', 'ÇAĞLAYANCERİT', 'DULKADİROĞLU', 'ONİKİŞUBAT', 'EKİNÖZÜ', 'NURHAK'];
$placeholders = implode(',', array_fill(0, count($districts), '?'));
$stmt = $db->prepare("SELECT DISTINCT defter_bolge FROM tanimlamalar WHERE grup = 'defter_kodu' AND defter_bolge NOT IN ($placeholders)");
$stmt->execute($districts);
print_r($stmt->fetchAll(PDO::FETCH_COLUMN));
