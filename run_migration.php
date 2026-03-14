<?php
try {
    $db = new PDO('mysql:host=localhost;dbname=mbeyazil_ersanelektrik;charset=utf8', 'root', '');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 1. Tanımlamalar - User already added this, but just in case
    try {
        $db->exec("ALTER TABLE tanimlamalar ADD COLUMN birden_fazla_personel_kullanabilir TINYINT(1) DEFAULT 0 AFTER tur_adi");
        echo "Added birden_fazla_personel_kullanabilir to tanimlamalar\n";
    } catch(Exception $e) { echo "tanimlamalar.birden_fazla_personel_kullanabilir already exists or error\n"; }

    // 2. Sayaç Değişim - Work distribution multiplier
    try {
        $db->exec("ALTER TABLE sayac_degisim ADD COLUMN is_sayisi DECIMAL(10,4) DEFAULT 1.0000 AFTER personel_id");
        echo "Added is_sayisi to sayac_degisim\n";
    } catch(Exception $e) { echo "sayac_degisim.is_sayisi already exists or error\n"; }

    // 3. Yapılan İşler - Change integer counts to decimal for division
    try {
        $db->exec("ALTER TABLE yapilan_isler MODIFY COLUMN sonuclanmis DECIMAL(10,2) DEFAULT 0.00");
        echo "Modified sonuclanmis in yapilan_isler to DECIMAL\n";
    } catch(Exception $e) { echo "Error modifying yapilan_isler.sonuclanmis: " . $e->getMessage() . "\n"; }

    try {
        $db->exec("ALTER TABLE yapilan_isler MODIFY COLUMN acik_olanlar DECIMAL(10,2) DEFAULT 0.00");
        echo "Modified acik_olanlar in yapilan_isler to DECIMAL\n";
    } catch(Exception $e) { echo "Error modifying yapilan_isler.acik_olanlar: " . $e->getMessage() . "\n"; }

    // 4. Endeks Okuma - Change integer counts to decimal for division
    try {
        $db->exec("ALTER TABLE endeks_okuma MODIFY COLUMN okunan_abone_sayisi DECIMAL(10,2) DEFAULT 0.00");
        echo "Modified okunan_abone_sayisi in endeks_okuma to DECIMAL\n";
    } catch(Exception $e) { echo "Error modifying endeks_okuma.okunan_abone_sayisi: " . $e->getMessage() . "\n"; }

} catch (Exception $e) {
    echo "CRITICAL ERROR: " . $e->getMessage() . "\n";
}
