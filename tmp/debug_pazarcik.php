<?php
require_once dirname(__DIR__) . '/bootstrap.php';
$db = (new \App\Model\Model('tanimlamalar'))->getDb();
$stmt = $db->query("SELECT COUNT(*) FROM tanimlamalar WHERE grup = 'defter_kodu' AND defter_bolge = 'PAZARCIK' AND CAST(tur_adi AS UNSIGNED) >= 600");
echo "Pazarcık Village Defters (>=600): " . $stmt->fetchColumn() . "\n";
$stmt = $db->query("SELECT COUNT(*) FROM tanimlamalar WHERE grup = 'defter_kodu' AND defter_bolge = 'PAZARCIK' AND CAST(tur_adi AS UNSIGNED) < 600");
echo "Pazarcık Center Defters (<600): " . $stmt->fetchColumn() . "\n";
