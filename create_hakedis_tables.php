<?php
require_once __DIR__ . '/bootstrap.php';

try {
    $db = new PDO("mysql:host=" . $_ENV['DB_HOST'] . ";dbname=" . $_ENV['DB_NAME'] . ";charset=utf8", $_ENV['DB_USER'], $_ENV['DB_PASS']);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $sqls = [
        "CREATE TABLE IF NOT EXISTS hakedis_sozlesmeler (
            id INT AUTO_INCREMENT PRIMARY KEY,
            firma_id INT NOT NULL,
            idare_adi VARCHAR(255) NOT NULL,
            isin_adi VARCHAR(500) NOT NULL,
            isin_yuklenicisi VARCHAR(255) NOT NULL,
            ihale_kayit_no VARCHAR(100),
            kesif_bedeli DECIMAL(15,2),
            ihale_tenzilati DECIMAL(10,5),
            sozlesme_bedeli DECIMAL(15,2),
            sozlesme_tarihi DATE,
            isin_bitecegi_tarih DATE,
            ihale_tarihi DATE,
            yer_teslim_tarihi DATE,
            isin_suresi INT,
            kontrol_teskilati TEXT,
            idare_onaylayan VARCHAR(255),
            idare_onaylayan_unvan VARCHAR(255),
            durum ENUM('aktif', 'pasif', 'tamamlandi') DEFAULT 'aktif',
            olusturma_tarihi DATETIME DEFAULT CURRENT_TIMESTAMP,
            silinme_tarihi DATETIME NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        "CREATE TABLE IF NOT EXISTS hakedis_kalemleri (
            id INT AUTO_INCREMENT PRIMARY KEY,
            sozlesme_id INT NOT NULL,
            kalem_adi VARCHAR(500) NOT NULL,
            birim VARCHAR(50) NOT NULL,
            miktari DECIMAL(15,2) DEFAULT 0,
            teklif_edilen_birim_fiyat DECIMAL(15,2) DEFAULT 0,
            agirlik_orani DECIMAL(10,5) DEFAULT 0,
            sira INT DEFAULT 0,
            silinme_tarihi DATETIME NULL,
            FOREIGN KEY (sozlesme_id) REFERENCES hakedis_sozlesmeler(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        "CREATE TABLE IF NOT EXISTS hakedis_donemleri (
            id INT AUTO_INCREMENT PRIMARY KEY,
            sozlesme_id INT NOT NULL,
            hakedis_no INT NOT NULL,
            hakedis_tarihi_ay INT NOT NULL,
            hakedis_tarihi_yil INT NOT NULL,
            is_yapilan_ayin_son_gunu DATE,
            temel_endeks_ayi VARCHAR(50),  
            guncel_endeks_ayi VARCHAR(50), 
            a1_katsayisi DECIMAL(10,5) DEFAULT 0.28,
            asgari_ucret_temel DECIMAL(15,2),
            asgari_ucret_guncel DECIMAL(15,2),
            b1_katsayisi DECIMAL(10,5) DEFAULT 0.22,
            motorin_temel DECIMAL(10,5),
            motorin_guncel DECIMAL(10,5),
            b2_katsayisi DECIMAL(10,5) DEFAULT 0.25,
            ufe_genel_temel DECIMAL(10,2),
            ufe_genel_guncel DECIMAL(10,2),
            c_katsayisi DECIMAL(10,5) DEFAULT 0.25,
            makine_ekipman_temel DECIMAL(10,2),
            makine_ekipman_guncel DECIMAL(10,2),
            sabit_katsayi_b DECIMAL(10,5) DEFAULT 0.90,
            avans_mahsubu DECIMAL(15,2) DEFAULT 0,
            kdv_orani DECIMAL(5,2) DEFAULT 20.00,
            tevkifat_orani VARCHAR(20) DEFAULT '4/10',
            damga_vergisi_orani DECIMAL(10,5) DEFAULT 0.00948,
            sozlesme_karar_pulu_orani DECIMAL(10,5) DEFAULT 0.00569,
            durum ENUM('taslak', 'onaylandi') DEFAULT 'taslak',
            olusturan_personel_id INT,
            olusturma_tarihi DATETIME DEFAULT CURRENT_TIMESTAMP,
            silinme_tarihi DATETIME NULL,
            FOREIGN KEY (sozlesme_id) REFERENCES hakedis_sozlesmeler(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        "CREATE TABLE IF NOT EXISTS hakedis_miktarlari (
            id INT AUTO_INCREMENT PRIMARY KEY,
            hakedis_donem_id INT NOT NULL,
            kalem_id INT NOT NULL,
            bolge_id INT NULL, 
            bolge_adi VARCHAR(50) NULL,
            miktar DECIMAL(15,2) DEFAULT 0,
            FOREIGN KEY (hakedis_donem_id) REFERENCES hakedis_donemleri(id) ON DELETE CASCADE,
            FOREIGN KEY (kalem_id) REFERENCES hakedis_kalemleri(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
    ];

    foreach ($sqls as $sql) {
        $db->exec($sql);
        echo "Executed query successfully.\n";
    }

    echo "\nHakedis tables created successfully!";

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
