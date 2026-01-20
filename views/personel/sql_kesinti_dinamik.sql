-- Personel Kesintileri Tablosu Güncellemesi
-- Sürekli kesinti ve oran bazlı hesaplama desteği için yeni alanlar

-- personel_kesintileri tablosuna yeni alanlar ekle (ALTER IGNORE kullanarak mevcut kolonlar varsa hata vermez)
-- MySQL 5.7+ uyumlu

-- Tekrar tipi ekleme
SET @column_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'personel_kesintileri' AND COLUMN_NAME = 'tekrar_tipi');
SET @query = IF(@column_exists = 0, 'ALTER TABLE personel_kesintileri ADD COLUMN tekrar_tipi ENUM(''tek_sefer'', ''surekli'') NOT NULL DEFAULT ''tek_sefer'' AFTER tur', 'SELECT 1');
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Başlangıç dönemi ekleme
SET @column_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'personel_kesintileri' AND COLUMN_NAME = 'baslangic_donemi');
SET @query = IF(@column_exists = 0, 'ALTER TABLE personel_kesintileri ADD COLUMN baslangic_donemi VARCHAR(7) NULL AFTER tekrar_tipi', 'SELECT 1');
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Bitiş dönemi ekleme
SET @column_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'personel_kesintileri' AND COLUMN_NAME = 'bitis_donemi');
SET @query = IF(@column_exists = 0, 'ALTER TABLE personel_kesintileri ADD COLUMN bitis_donemi VARCHAR(7) NULL AFTER baslangic_donemi', 'SELECT 1');
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Hesaplama tipi ekleme
SET @column_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'personel_kesintileri' AND COLUMN_NAME = 'hesaplama_tipi');
SET @query = IF(@column_exists = 0, 'ALTER TABLE personel_kesintileri ADD COLUMN hesaplama_tipi ENUM(''sabit'', ''oran_net'', ''oran_brut'') NOT NULL DEFAULT ''sabit'' AFTER bitis_donemi', 'SELECT 1');
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Oran ekleme
SET @column_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'personel_kesintileri' AND COLUMN_NAME = 'oran');
SET @query = IF(@column_exists = 0, 'ALTER TABLE personel_kesintileri ADD COLUMN oran DECIMAL(5,2) NULL AFTER hesaplama_tipi', 'SELECT 1');
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Parametre ID ekleme
SET @column_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'personel_kesintileri' AND COLUMN_NAME = 'parametre_id');
SET @query = IF(@column_exists = 0, 'ALTER TABLE personel_kesintileri ADD COLUMN parametre_id INT NULL AFTER oran', 'SELECT 1');
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Ana kesinti ID ekleme
SET @column_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'personel_kesintileri' AND COLUMN_NAME = 'ana_kesinti_id');
SET @query = IF(@column_exists = 0, 'ALTER TABLE personel_kesintileri ADD COLUMN ana_kesinti_id INT NULL AFTER parametre_id', 'SELECT 1');
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Aktif ekleme
SET @column_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'personel_kesintileri' AND COLUMN_NAME = 'aktif');
SET @query = IF(@column_exists = 0, 'ALTER TABLE personel_kesintileri ADD COLUMN aktif TINYINT(1) DEFAULT 1 AFTER ana_kesinti_id', 'SELECT 1');
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Updated_at ekleme
SET @column_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'personel_kesintileri' AND COLUMN_NAME = 'updated_at');
SET @query = IF(@column_exists = 0, 'ALTER TABLE personel_kesintileri ADD COLUMN updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP', 'SELECT 1');
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =====================================================================
-- personel_ek_odemeler tablosuna yeni alanlar ekle
-- =====================================================================

-- Tekrar tipi ekleme
SET @column_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'personel_ek_odemeler' AND COLUMN_NAME = 'tekrar_tipi');
SET @query = IF(@column_exists = 0, 'ALTER TABLE personel_ek_odemeler ADD COLUMN tekrar_tipi ENUM(''tek_sefer'', ''surekli'') NOT NULL DEFAULT ''tek_sefer'' AFTER tur', 'SELECT 1');
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Başlangıç dönemi ekleme
SET @column_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'personel_ek_odemeler' AND COLUMN_NAME = 'baslangic_donemi');
SET @query = IF(@column_exists = 0, 'ALTER TABLE personel_ek_odemeler ADD COLUMN baslangic_donemi VARCHAR(7) NULL AFTER tekrar_tipi', 'SELECT 1');
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Bitiş dönemi ekleme
SET @column_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'personel_ek_odemeler' AND COLUMN_NAME = 'bitis_donemi');
SET @query = IF(@column_exists = 0, 'ALTER TABLE personel_ek_odemeler ADD COLUMN bitis_donemi VARCHAR(7) NULL AFTER baslangic_donemi', 'SELECT 1');
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Hesaplama tipi ekleme
SET @column_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'personel_ek_odemeler' AND COLUMN_NAME = 'hesaplama_tipi');
SET @query = IF(@column_exists = 0, 'ALTER TABLE personel_ek_odemeler ADD COLUMN hesaplama_tipi ENUM(''sabit'', ''oran_net'', ''oran_brut'') NOT NULL DEFAULT ''sabit'' AFTER bitis_donemi', 'SELECT 1');
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Oran ekleme
SET @column_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'personel_ek_odemeler' AND COLUMN_NAME = 'oran');
SET @query = IF(@column_exists = 0, 'ALTER TABLE personel_ek_odemeler ADD COLUMN oran DECIMAL(5,2) NULL AFTER hesaplama_tipi', 'SELECT 1');
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Parametre ID ekleme
SET @column_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'personel_ek_odemeler' AND COLUMN_NAME = 'parametre_id');
SET @query = IF(@column_exists = 0, 'ALTER TABLE personel_ek_odemeler ADD COLUMN parametre_id INT NULL AFTER oran', 'SELECT 1');
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Ana ödeme ID ekleme
SET @column_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'personel_ek_odemeler' AND COLUMN_NAME = 'ana_odeme_id');
SET @query = IF(@column_exists = 0, 'ALTER TABLE personel_ek_odemeler ADD COLUMN ana_odeme_id INT NULL AFTER parametre_id', 'SELECT 1');
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Aktif ekleme
SET @column_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'personel_ek_odemeler' AND COLUMN_NAME = 'aktif');
SET @query = IF(@column_exists = 0, 'ALTER TABLE personel_ek_odemeler ADD COLUMN aktif TINYINT(1) DEFAULT 1 AFTER ana_odeme_id', 'SELECT 1');
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Updated_at ekleme
SET @column_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'personel_ek_odemeler' AND COLUMN_NAME = 'updated_at');
SET @query = IF(@column_exists = 0, 'ALTER TABLE personel_ek_odemeler ADD COLUMN updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP', 'SELECT 1');
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =====================================================================
-- Bordro parametrelerine oran alanı ekle (eğer yoksa)
-- =====================================================================

SET @column_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'bordro_parametreleri' AND COLUMN_NAME = 'oran');
SET @query = IF(@column_exists = 0, 'ALTER TABLE bordro_parametreleri ADD COLUMN oran DECIMAL(5,2) NULL AFTER varsayilan_tutar', 'SELECT 1');
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =====================================================================
-- BES parametresi ekle (eğer yoksa)
-- =====================================================================

INSERT INTO bordro_parametreleri (kod, etiket, kategori, hesaplama_tipi, oran, varsayilan_tutar, sira, aktif, gecerlilik_baslangic) 
SELECT 'bes', 'Bireysel Emeklilik (BES)', 'kesinti', 'net', 3.00, 0, 5, 1, '2026-01-01'
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM bordro_parametreleri WHERE kod = 'bes');

-- =====================================================================
-- Başarılı mesajı
-- =====================================================================
SELECT 'Migration tamamlandı! Yeni alanlar eklendi.' as Sonuc;
