-- Gelir-gider tablosundaki geçmiş mükerrer kayıtları soft delete ile temizler.
-- Her mükerrer grupta en küçük ID korunur; diğer aktif kayıtlar pasife alınır.
-- Çalıştırmadan önce aşağıdaki kullanıcı ID'sini işlemi yapan yönetici ID'siyle değiştirin.
SET @cleanup_user_id := 0;

DROP TEMPORARY TABLE IF EXISTS `tmp_duplicate_gelir_gider_ids`;

CREATE TEMPORARY TABLE `tmp_duplicate_gelir_gider_ids` (
    `id` INT NOT NULL,
    `kept_id` INT NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_tmp_duplicate_kept_id` (`kept_id`)
) ENGINE=InnoDB;

INSERT INTO `tmp_duplicate_gelir_gider_ids` (`id`, `kept_id`)
SELECT
    duplicate_row.id,
    MIN(original_row.id) AS kept_id
FROM `gelir_gider` AS duplicate_row
INNER JOIN `gelir_gider` AS original_row
    ON original_row.silinme_tarihi IS NULL
   AND original_row.id < duplicate_row.id
   AND original_row.type = duplicate_row.type
   AND original_row.tarih <=> duplicate_row.tarih
   AND ROUND(CAST(original_row.tutar AS DECIMAL(15, 2)), 2)
       = ROUND(CAST(duplicate_row.tutar AS DECIMAL(15, 2)), 2)
   AND LOWER(TRIM(COALESCE(original_row.kategori, '')))
       = LOWER(TRIM(COALESCE(duplicate_row.kategori, '')))
   AND LOWER(TRIM(COALESCE(original_row.hesap_adi, '')))
       = LOWER(TRIM(COALESCE(duplicate_row.hesap_adi, '')))
   AND LOWER(TRIM(COALESCE(original_row.aciklama, '')))
       = LOWER(TRIM(COALESCE(duplicate_row.aciklama, '')))
   AND LOWER(TRIM(COALESCE(original_row.plaka, '')))
       = LOWER(TRIM(COALESCE(duplicate_row.plaka, '')))
   AND LOWER(TRIM(COALESCE(original_row.odeme_sekli, '')))
       = LOWER(TRIM(COALESCE(duplicate_row.odeme_sekli, '')))
   AND LOWER(TRIM(COALESCE(original_row.banka_adi, '')))
       = LOWER(TRIM(COALESCE(duplicate_row.banka_adi, '')))
WHERE duplicate_row.silinme_tarihi IS NULL
GROUP BY duplicate_row.id;

-- ÖNİZLEME: Silinecek kayıtları ve korunacak kayıt ID'sini gösterir.
SELECT
    duplicate_ids.kept_id AS korunacak_id,
    duplicate_row.*
FROM `tmp_duplicate_gelir_gider_ids` AS duplicate_ids
INNER JOIN `gelir_gider` AS duplicate_row ON duplicate_row.id = duplicate_ids.id
ORDER BY duplicate_ids.kept_id, duplicate_row.id;

-- ÖNİZLEME: Toplam soft delete edilecek kayıt sayısı.
SELECT COUNT(*) AS soft_delete_edilecek_kayit_sayisi
FROM `tmp_duplicate_gelir_gider_ids`;

START TRANSACTION;

UPDATE `gelir_gider` AS gelir_gider_row
INNER JOIN `tmp_duplicate_gelir_gider_ids` AS duplicate_ids
    ON duplicate_ids.id = gelir_gider_row.id
SET
    gelir_gider_row.silinme_tarihi = NOW(),
    gelir_gider_row.silen_kullanici = @cleanup_user_id,
    gelir_gider_row.duplicate_hash = NULL
WHERE gelir_gider_row.silinme_tarihi IS NULL;

SELECT ROW_COUNT() AS soft_delete_edilen_kayit_sayisi;

COMMIT;

DROP TEMPORARY TABLE IF EXISTS `tmp_duplicate_gelir_gider_ids`;
