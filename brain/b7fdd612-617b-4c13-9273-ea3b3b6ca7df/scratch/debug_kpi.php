<?php
session_start();
$_SESSION['id'] = 1;

$db = new PDO("mysql:host=localhost;dbname=ersantrc_personel;charset=utf8", "root", "");
$stmt = $db->prepare("SELECT id FROM personel WHERE adi_soyadi LIKE '%BURHAN GÖK%'");
$stmt->execute();
$personel_id = $stmt->fetchColumn();

// Simulate API POST request parameters
$firma_id = 1;
$iade_tarihi = date('d.m.Y');
$adet = 1381;
$sayac_adi = "";
$aciklama = "Test from debug";
$directKaski = false;

echo "Mode: manual, P_ID: $personel_id, Adet: $adet, DirectKaski: $directKaski\n";

$sqlKat = $db->prepare("SELECT id FROM tanimlamalar WHERE grup = 'demirbas_kategorisi' AND (LOWER(tur_adi) LIKE '%sayaç%' OR LOWER(tur_adi) LIKE '%sayac%') AND firma_id = ? LIMIT 1");
$sqlKat->execute([$firma_id]);
$sayacKatId = $sqlKat->fetchColumn();

if (!$sayacKatId) {
    die("Error: Sayaç kategorisi bulunamadı.\n");
}
echo "Kategori ID: $sayacKatId\n";

$formatted_tarih = date('Y-m-d');
if (empty($sayac_adi)) {
    $sayac_adi = "Hurda Sayaç (Manuel İade)";
}

try {
    $db->beginTransaction();

    $status = $directKaski ? 'Kaskiye Teslim Edildi' : 'hurda';
    $lokasyon = $directKaski ? 'kaski' : 'bizim_depo';
    $kalan_miktar = $directKaski ? 0 : $adet;

    // 1. Hurda sayaç demirbaş kaydı oluştur
    $sqlInsert = $db->prepare("
        INSERT INTO demirbas 
        (firma_id, kategori_id, demirbas_adi, miktar, kalan_miktar, durum, lokasyon, aciklama, kayit_yapan, edinme_tarihi, kaskiye_teslim_tarihi, kaskiye_teslim_eden)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $pName = $db->query("SELECT adi_soyadi FROM personel WHERE id = $personel_id")->fetchColumn();

    $executeResult = $sqlInsert->execute([
        $firma_id,
        $sayacKatId,
        $sayac_adi,
        $adet,
        $kalan_miktar,
        $status,
        $lokasyon,
        "Manuel Hurda İade: " . $aciklama,
        $_SESSION['id'],
        $formatted_tarih,
        $directKaski ? $formatted_tarih : null,
        $directKaski ? ($pName ?: null) : null
    ]);

    $yeniDemirbasId = $db->lastInsertId();
    echo "Demirbas Inserted: $executeResult, ID: $yeniDemirbasId\n";

    // 2. İade hareketini ekle
    $iadeAciklama = $directKaski ? "[KASKI_TESLIM] Personelden doğrudan KASKİ'ye teslim. " : "[HURDA_IADE] Hurda Sayaç İade. ";
    if ($aciklama) {
        $iadeAciklama .= "Not: " . $aciklama;
    } else {
        $iadeAciklama .= "(Manuel Giriş)";
    }

    $sqlHareket = $db->prepare("
        INSERT INTO demirbas_hareketler 
        (demirbas_id, personel_id, hareket_tipi, miktar, tarih, aciklama, islem_yapan_id, kaynak)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $exHareket = $sqlHareket->execute([
        $yeniDemirbasId,
        $personel_id,
        'iade',
        $adet,
        $formatted_tarih,
        $iadeAciklama,
        $_SESSION['id'],
        'manuel'
    ]);
    echo "Hareket Inserted: $exHareket\n";

    $db->rollBack(); // DONT REALLY COMMIT
    echo "SUCCESS: Everything ran perfectly!\n";

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    echo "Exception: " . $e->getMessage() . "\n";
}
