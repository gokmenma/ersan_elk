-- Belirli bir sayaç seri numarasındaki geçmiş mükerrer aktif zimmetleri listeler ve soft delete eder.
-- Mükerrer anahtarı: firma_id + normalize seri_no + personel_id + teslim_tarihi + aktif teslim durumu.
-- Aynı seri numarası farklı demirbas_id kayıtlarında bulunsa da mükerrer kabul edilir.
-- Her grupta en küçük zimmet ID'si korunur; daha büyük ID'ler mükerrer kabul edilir.
-- Fiziksel DELETE yapılmaz. Zimmet ve ona bağlı hareketler silinme_tarihi ile pasifleştirilir.
-- Varsayılan 0 yalnızca önizleme yapar. Kontrol sonrası temizliği uygulamak için 1 yapın.
SET @apply_cleanup := 0;

-- ZORUNLU HEDEF: Yalnızca bu seri numarası incelenir ve temizlenir.
-- Başka bir seri için scripti yeniden çalıştırmadan önce bu değeri değiştirin.
SET @target_serial_no := '26128761';

DROP TEMPORARY TABLE IF EXISTS `tmp_duplicate_sayac_zimmet_ids`;

CREATE TEMPORARY TABLE `tmp_duplicate_sayac_zimmet_ids` (
    `duplicate_id` INT NOT NULL,
    `kept_id` INT NOT NULL,
    PRIMARY KEY (`duplicate_id`),
    KEY `idx_tmp_sayac_zimmet_kept_id` (`kept_id`)
) ENGINE=InnoDB;

INSERT INTO `tmp_duplicate_sayac_zimmet_ids` (`duplicate_id`, `kept_id`)
SELECT
    duplicate_zimmet.id AS duplicate_id,
    MIN(original_zimmet.id) AS kept_id
FROM `demirbas_zimmet` AS duplicate_zimmet
INNER JOIN `demirbas` AS demirbas_row
    ON demirbas_row.id = duplicate_zimmet.demirbas_id
   AND demirbas_row.silinme_tarihi IS NULL
INNER JOIN `tanimlamalar` AS kategori
    ON kategori.id = demirbas_row.kategori_id
   AND kategori.grup = 'demirbas_kategorisi'
   AND kategori.silinme_tarihi IS NULL
   AND (
       LOWER(kategori.tur_adi) LIKE '%sayaç%'
       OR LOWER(kategori.tur_adi) LIKE '%sayac%'
   )
INNER JOIN `demirbas_zimmet` AS original_zimmet
    ON original_zimmet.personel_id = duplicate_zimmet.personel_id
   AND original_zimmet.teslim_tarihi = duplicate_zimmet.teslim_tarihi
   AND original_zimmet.durum = 'teslim'
   AND original_zimmet.silinme_tarihi IS NULL
   AND original_zimmet.id < duplicate_zimmet.id
INNER JOIN `demirbas` AS original_demirbas
    ON original_demirbas.id = original_zimmet.demirbas_id
   AND original_demirbas.firma_id = demirbas_row.firma_id
   AND original_demirbas.silinme_tarihi IS NULL
   AND (
       original_demirbas.id = demirbas_row.id
       OR (
           NULLIF(TRIM(original_demirbas.seri_no), '') IS NOT NULL
           AND LOWER(TRIM(original_demirbas.seri_no)) = LOWER(TRIM(demirbas_row.seri_no))
       )
   )
WHERE duplicate_zimmet.durum = 'teslim'
  AND duplicate_zimmet.silinme_tarihi IS NULL
  AND NULLIF(TRIM(@target_serial_no), '') IS NOT NULL
  AND LOWER(TRIM(demirbas_row.seri_no)) = LOWER(TRIM(@target_serial_no))
GROUP BY duplicate_zimmet.id;

-- ÖNİZLEME: Korunacak ve soft delete edilecek sayaç zimmetlerini gösterir.
SELECT
    duplicate_ids.kept_id AS korunacak_zimmet_id,
    duplicate_ids.duplicate_id AS silinecek_zimmet_id,
    kept_zimmet.demirbas_id AS korunacak_demirbas_id,
    duplicate_zimmet.demirbas_id AS silinecek_demirbas_id,
    demirbas_row.demirbas_no,
    demirbas_row.demirbas_adi,
    demirbas_row.marka,
    demirbas_row.model,
    demirbas_row.seri_no,
    personel.adi_soyadi AS personel_adi,
    duplicate_zimmet.teslim_tarihi,
    duplicate_zimmet.teslim_miktar,
    duplicate_zimmet.kayit_tarihi
FROM `tmp_duplicate_sayac_zimmet_ids` AS duplicate_ids
INNER JOIN `demirbas_zimmet` AS duplicate_zimmet
    ON duplicate_zimmet.id = duplicate_ids.duplicate_id
INNER JOIN `demirbas_zimmet` AS kept_zimmet
    ON kept_zimmet.id = duplicate_ids.kept_id
INNER JOIN `demirbas` AS demirbas_row
    ON demirbas_row.id = duplicate_zimmet.demirbas_id
INNER JOIN `personel` AS personel
    ON personel.id = duplicate_zimmet.personel_id
ORDER BY duplicate_ids.kept_id, duplicate_ids.duplicate_id;

-- ÖNİZLEME: Etkilenecek zimmet ve hareket adetlerini gösterir.
SELECT
    COUNT(*) AS soft_delete_edilecek_zimmet
FROM `tmp_duplicate_sayac_zimmet_ids`;

SELECT
    COUNT(*) AS soft_delete_edilecek_hareket
FROM `demirbas_hareketler` AS hareket
INNER JOIN `tmp_duplicate_sayac_zimmet_ids` AS duplicate_ids
    ON duplicate_ids.duplicate_id = hareket.zimmet_id
WHERE hareket.silinme_tarihi IS NULL;

START TRANSACTION;

-- Önce mükerrer zimmetlere bağlı hareket kayıtlarını pasifleştir.
UPDATE `demirbas_hareketler` AS hareket
INNER JOIN `tmp_duplicate_sayac_zimmet_ids` AS duplicate_ids
    ON duplicate_ids.duplicate_id = hareket.zimmet_id
SET hareket.silinme_tarihi = NOW()
WHERE hareket.silinme_tarihi IS NULL
  AND @apply_cleanup = 1;

SELECT ROW_COUNT() AS soft_delete_edilen_hareket_sayisi;

-- Ardından mükerrer zimmet kayıtlarını pasifleştir.
UPDATE `demirbas_zimmet` AS zimmet
INNER JOIN `tmp_duplicate_sayac_zimmet_ids` AS duplicate_ids
    ON duplicate_ids.duplicate_id = zimmet.id
SET zimmet.silinme_tarihi = NOW()
WHERE zimmet.silinme_tarihi IS NULL
  AND @apply_cleanup = 1;

SELECT ROW_COUNT() AS soft_delete_edilen_zimmet_sayisi;

COMMIT;

-- DOĞRULAMA: @apply_cleanup = 1 ile çalıştırıldığında sonuç sıfır satır dönmelidir.
SELECT
    demirbas_row.firma_id,
    LOWER(TRIM(demirbas_row.seri_no)) AS normalize_seri_no,
    zimmet.personel_id,
    zimmet.teslim_tarihi,
    COUNT(*) AS aktif_mukerrer_adedi,
    GROUP_CONCAT(zimmet.id ORDER BY zimmet.id) AS zimmet_idleri
FROM `demirbas_zimmet` AS zimmet
INNER JOIN `demirbas` AS demirbas_row
    ON demirbas_row.id = zimmet.demirbas_id
INNER JOIN `tanimlamalar` AS kategori
    ON kategori.id = demirbas_row.kategori_id
   AND kategori.grup = 'demirbas_kategorisi'
WHERE zimmet.durum = 'teslim'
  AND zimmet.silinme_tarihi IS NULL
  AND kategori.silinme_tarihi IS NULL
  AND NULLIF(TRIM(@target_serial_no), '') IS NOT NULL
  AND LOWER(TRIM(demirbas_row.seri_no)) = LOWER(TRIM(@target_serial_no))
  AND (
      LOWER(kategori.tur_adi) LIKE '%sayaç%'
      OR LOWER(kategori.tur_adi) LIKE '%sayac%'
  )
  AND NULLIF(TRIM(demirbas_row.seri_no), '') IS NOT NULL
GROUP BY
    demirbas_row.firma_id,
    LOWER(TRIM(demirbas_row.seri_no)),
    zimmet.personel_id,
    zimmet.teslim_tarihi
HAVING COUNT(*) > 1;

DROP TEMPORARY TABLE IF EXISTS `tmp_duplicate_sayac_zimmet_ids`;
