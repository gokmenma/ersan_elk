<?php
require_once __DIR__ . '/bootstrap.php';
$db = getDbConnection();

// Hangi tarih aralığında 5 adet ve 13185.20 çıkar?
$ranges = [
    ['2026-10-01', '2026-10-06'],
    ['2026-10-01', '2026-10-05'],
    ['2026-10-01', '2026-10-07'],
];

foreach ($ranges as $r) {
    $c = $db->query("SELECT COUNT(*) FROM faturalar WHERE firm_id=1 AND yon='GELEN' AND deleted_at IS NULL AND fatura_tarihi BETWEEN '{$r[0]}' AND '{$r[1]}'")->fetchColumn();
    $s = $db->query("SELECT SUM(odenecek_tutar) FROM faturalar WHERE firm_id=1 AND yon='GELEN' AND deleted_at IS NULL AND fatura_tarihi BETWEEN '{$r[0]}' AND '{$r[1]}'")->fetchColumn();
    echo "Aralık: {$r[0]} - {$r[1]} => Adet: $c, Tutar: $s\n";
}
