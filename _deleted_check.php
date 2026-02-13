<?php
require_once __DIR__ . '/Autoloader.php';
$db = new App\Core\Db();
$pdo = $db->db;

$id = 75;
echo "\n--- ALL KESINTILER FOR PERSONEL 75 (INCLUDING DELETED) ---\n";
$stmt = $pdo->query("SELECT pk.id, pk.icra_id, pk.tutar, pk.tur, pk.durum, pk.aciklama, bd.donem_adi, pk.silinme_tarihi
                       FROM personel_kesintileri pk 
                       LEFT JOIN bordro_donemi bd ON pk.donem_id = bd.id
                       WHERE pk.personel_id = $id
                       ORDER BY pk.id DESC");
$res = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($res as $r) {
    echo "ID: {$r['id']} | Tur: {$r['tur']} | Tutar: {$r['tutar']} | IcraID: " . ($r['icra_id'] ?: 'NULL') . " | Durum: {$r['durum']} | Silinme: " . ($r['silinme_tarihi'] ?: 'NO') . " | Donem: {$r['donem_adi']} | Aciklama: {$r['aciklama']}\n";
}
