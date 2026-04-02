<?php
require 'bootstrap.php';
$db = (new \App\Model\Model())->db;

echo "--- Puantaj Veri Tutarlılık Onarım Başladı ---\n";

// 1. Tanımlamalar Tablosu Onarımı (Boş Sekmeleri Doldurma)
echo "1. Tanımlamalar tablosunda boş sekmeler onarılıyor...\n";
$updates = [
    'kesme' => ['KESME', 'AÇMA', 'SUYU KES', 'SUYU AÇ'],
    'sokme_takma' => ['SÖKME', 'TAKMA', 'SAYAÇ DEĞİŞME', 'DEGISME'],
    'muhurleme' => ['MÜHÜR'],
];

foreach ($updates as $sekme => $keywords) {
    foreach ($keywords as $kw) {
        $sql = "UPDATE tanimlamalar SET rapor_sekmesi = ? 
                WHERE (tur_adi LIKE ? OR is_emri_sonucu LIKE ?) 
                AND (rapor_sekmesi IS NULL OR rapor_sekmesi = '0' OR rapor_sekmesi = '') 
                AND grup = 'is_turu' AND silinme_tarihi IS NULL";
        $stmt = $db->prepare($sql);
        $stmt->execute([$sekme, "%$kw%", "%$kw%"]);
        echo " - '$kw' anahtar kelimesi için $sekme sekmesine " . $stmt->rowCount() . " kayıt atandı.\n";
    }
}

// 2. Yapılan İşler Tablosu Onarımı (Orphaned veya Yanlış ID'ler)
echo "2. Yapılan İşler tablosunda kategori eşitlemesi yapılıyor...\n";

// Eğer bir işlemin ID'si '0' olan bir tanıma bağlıysa ama aynı isimde ücretli bir tanım varsa, ücretli olana çek
$sqlMerge = "SELECT DISTINCT t.is_emri_tipi, t.is_emri_sonucu, t.firma_id 
             FROM yapilan_isler t
             LEFT JOIN tanimlamalar tn ON t.is_emri_sonucu_id = tn.id
             WHERE (tn.rapor_sekmesi IS NULL OR tn.rapor_sekmesi = '0' OR tn.id IS NULL)
             AND t.silinme_tarihi IS NULL";
$rows = $db->query($sqlMerge)->fetchAll(PDO::FETCH_OBJ);

$mergedCount = 0;
foreach ($rows as $row) {
    // Aynı isimde ve rapor sekmesi olan 'gerçek' bir tanım ara
    $sqlFind = "SELECT id FROM tanimlamalar 
                WHERE TRIM(tur_adi) = ? AND TRIM(is_emri_sonucu) = ? 
                AND firma_id = ? AND rapor_sekmesi != '0' AND rapor_sekmesi IS NOT NULL 
                AND silinme_tarihi IS NULL LIMIT 1";
    $stmtFind = $db->prepare($sqlFind);
    $stmtFind->execute([trim($row->is_emri_tipi), trim($row->is_emri_sonucu), $row->firma_id]);
    $newId = $stmtFind->fetchColumn();
    
    if ($newId) {
        $sqlUpdate = "UPDATE yapilan_isler SET is_emri_sonucu_id = ? 
                      WHERE is_emri_tipi = ? AND is_emri_sonucu = ? AND firma_id = ? AND silinme_tarihi IS NULL";
        $stmtUpd = $db->prepare($sqlUpdate);
        $stmtUpd->execute([$newId, $row->is_emri_tipi, $row->is_emri_sonucu, $row->firma_id]);
        $mergedCount += $stmtUpd->rowCount();
    }
}
echo " - $mergedCount kayıt doğru kategoriye (Paid/Mapped) aktarıldı.\n";

// 3. SKA Özel Eşitleme
echo "3. Sayaç Kullanıma açıldı (SKA) eşitlemesi...\n";
$sqlSKA = "UPDATE yapilan_isler t
           SET is_emri_sonucu_id = (SELECT id FROM tanimlamalar WHERE TRIM(is_emri_sonucu) = 'Sayaç Kullanıma açıldı' AND firma_id = t.firma_id AND rapor_sekmesi = 'kesme' AND silinme_tarihi IS NULL LIMIT 1)
           WHERE t.is_emri_sonucu = 'Sayaç Kullanıma açıldı' 
           AND (t.is_emri_sonucu_id IS NULL OR t.is_emri_sonucu_id NOT IN (SELECT id FROM tanimlamalar WHERE rapor_sekmesi = 'kesme' AND silinme_tarihi IS NULL))";
$resSKA = $db->exec($sqlSKA);
echo " - $resSKA SKA kaydı canonical ID ile eşitlendi.\n";

echo "--- Onarım Tamamlandı ---\n";
