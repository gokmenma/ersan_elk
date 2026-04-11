<?php
require_once 'vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->load();

$db = (new App\Core\Db())->db;
$personel_id = 84;
$donem_id = 20;

// Hesaplama sonrası ek ödeme tablosundaki güncel tutarı kontrol et
$sql = "SELECT peo.id, peo.tur, peo.tutar, peo.aciklama 
        FROM personel_ek_odemeler peo 
        WHERE peo.personel_id = ? AND peo.donem_id = ? AND peo.silinme_tarihi IS NULL AND peo.tur = 'yemek_yardimi_tum'";
$stmt = $db->prepare($sql);
$stmt->execute([$personel_id, $donem_id]);
$records = $stmt->fetchAll(PDO::FETCH_OBJ);

echo "=== Hesaplama sonrasi yemek_yardimi_tum kayitlari ===\n";
foreach ($records as $r) {
    echo "ID: {$r->id} | Tutar: {$r->tutar} | Aciklama: {$r->aciklama}\n";
}

// Hesaplama detayını da kontrol et
$sql = "SELECT hesaplama_detay FROM bordro_personel WHERE personel_id = ? AND donem_id = ? AND silinme_tarihi IS NULL";
$stmt = $db->prepare($sql);
$stmt->execute([$personel_id, $donem_id]);
$bp = $stmt->fetch(PDO::FETCH_OBJ);

if ($bp && $bp->hesaplama_detay) {
    $detay = json_decode($bp->hesaplama_detay, true);
    echo "\n=== Hesaplama Detay (JSON) - Ek Odemeler ===\n";
    if (isset($detay['ek_odemeler'])) {
        foreach ($detay['ek_odemeler'] as $eo) {
            if ($eo['kod'] === 'yemek_yardimi_tum') {
                echo "Kod: {$eo['kod']}\n";
                echo "Tutar (gunluk): {$eo['tutar']}\n";
                echo "Gun Sayisi: " . ($eo['gun_sayisi'] ?? 'N/A') . "\n";
                echo "Hesaplanan Tutar: " . ($eo['hesaplanan_tutar'] ?? 'N/A') . "\n";
                echo "Gun Kaynak: " . ($eo['gun_kaynak'] ?? 'N/A') . "\n";
            }
        }
    }
    echo "\n=== Toplam Ek Odeme: " . ($detay['ozet']['net_ek_odemeler'] ?? 'N/A') . " ===\n";
}
