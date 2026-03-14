<?php
try {
    $db = new PDO('mysql:host=localhost;dbname=mbeyazil_ersanelektrik;charset=utf8', 'root', '');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $ekipId = 1657; // EKİP-51
    $firmaId = 1;
    $dates = ['2026-03-10', '2026-03-11', '2026-03-12', '2026-03-13'];

    // Get personnel for these dates
    $stmtHist = $db->prepare("SELECT * FROM personel_ekip_gecmisi WHERE ekip_kodu_id = ?");
    $stmtHist->execute([$ekipId]);
    $ekipGecmisi = $stmtHist->fetchAll(PDO::FETCH_ASSOC);

    foreach ($dates as $date) {
        $personelMatches = [];
        foreach ($ekipGecmisi as $hist) {
            if ($hist['baslangic_tarihi'] <= $date && ($hist['bitis_tarihi'] === null || $hist['bitis_tarihi'] >= $date)) {
                $personelMatches[] = $hist['personel_id'];
            }
        }

        if (count($personelMatches) > 1) {
            echo "Distributing for $date (Personnel: " . implode(', ', $personelMatches) . ")\n";
            
            // Find current records for this team and date
            $stmtIsler = $db->prepare("SELECT * FROM yapilan_isler WHERE ekip_kodu_id = ? AND tarih = ? AND silinme_tarihi IS NULL");
            $stmtIsler->execute([$ekipId, $date]);
            $isler = $stmtIsler->fetchAll(PDO::FETCH_ASSOC);

            foreach ($isler as $is) {
                // If it's already distributed (aciklama contains 'bölündü'), skip or redo
                // These specifically don't have aciklama in the debug output.
                
                $db->beginTransaction();
                
                // Soft delete the original
                $stmtDel = $db->prepare("UPDATE yapilan_isler SET silinme_tarihi = NOW() WHERE id = ?");
                $stmtDel->execute([$is['id']]);

                $personelSayisi = count($personelMatches);
                $bolunmusSonuclanmis = $is['sonuclanmis'] / $personelSayisi;
                $bolunmusAcikOlanlar = $is['acik_olanlar'] / $personelSayisi;
                $ekAciklama = " (İş $personelSayisi kişiye bölündü. Toplam: {$is['sonuclanmis']})";

                foreach ($personelMatches as $pId) {
                    $perPersonIslemId = $is['islem_id'] . '_' . $pId;
                    $stmtIns = $db->prepare("INSERT INTO yapilan_isler (islem_id, personel_id, ekip_kodu_id, firma_id, is_emri_sonucu_id, is_emri_tipi, ekip_kodu, is_emri_sonucu, sonuclanmis, acik_olanlar, tarih, aciklama) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
                    $stmtIns->execute([
                        $perPersonIslemId,
                        $pId,
                        $is['ekip_kodu_id'],
                        $is['firma_id'],
                        $is['is_emri_sonucu_id'],
                        $is['is_emri_tipi'],
                        $is['ekip_kodu'],
                        $is['is_emri_sonucu'],
                        $bolunmusSonuclanmis,
                        $bolunmusAcikOlanlar,
                        $is['tarih'],
                        "Fixing distribution" . $ekAciklama
                    ]);
                }
                
                $db->commit();
                echo "  Processed Job ID {$is['id']}\n";
            }
        } else {
            echo "Only one personnel ($personelMatches[0]) for $date. Skipping.\n";
        }
    }

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    echo "ERROR: " . $e->getMessage() . "\n";
}
