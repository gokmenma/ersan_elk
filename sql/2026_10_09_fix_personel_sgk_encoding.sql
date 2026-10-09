-- SGK firma alanlarini tek karakter setinde birlestirir ve daha once "?" olarak
-- kaydedilmis, anlami diger alanlardan kesin olarak belirlenebilen degerleri onarir.
-- Calistirmadan once veritabani yedegi alinmalidir.

ALTER TABLE personel
    MODIFY sgk_yapilan_firma VARCHAR(150)
    CHARACTER SET utf8mb4 COLLATE utf8mb4_turkish_ci NULL DEFAULT NULL;

ALTER TABLE personel_calisma_gecmisi
    MODIFY sgk_yapilan_firma VARCHAR(100)
    CHARACTER SET utf8mb4 COLLATE utf8mb4_turkish_ci NULL DEFAULT 'Yok';

START TRANSACTION;

UPDATE personel
SET sgk_yapilan_firma = CASE
    WHEN disardan_sigortali = 1 THEN 'Dışarıdan Sigortalı'
    WHEN sgk_yapilan_firma = '??KUR' THEN 'İŞKUR'
    WHEN sgk_yapilan_firma = 'ER-SAN ELEKTR?K ?N?.TAAH.LTD.?T?.'
        THEN 'ER-SAN ELEKTRİK İNŞ.TAAH.LTD.ŞTİ.'
    ELSE sgk_yapilan_firma
END
WHERE sgk_yapilan_firma LIKE '%?%';

UPDATE personel_calisma_gecmisi
SET sgk_yapilan_firma = CASE
    WHEN disardan_sigortali = 1 THEN 'Dışarıdan Sigortalı'
    WHEN sgk_yapilan_firma = '??KUR' THEN 'İŞKUR'
    WHEN sgk_yapilan_firma = 'ER-SAN ELEKTR?K ?N?.TAAH.LTD.?T?.'
        THEN 'ER-SAN ELEKTRİK İNŞ.TAAH.LTD.ŞTİ.'
    ELSE sgk_yapilan_firma
END
WHERE sgk_yapilan_firma LIKE '%?%';

COMMIT;

-- Bu sorgular her iki tabloda da 0 donmelidir.
SELECT COUNT(*) AS bozuk_personel_sgk
FROM personel
WHERE sgk_yapilan_firma LIKE '%?%';

SELECT COUNT(*) AS bozuk_calisma_gecmisi_sgk
FROM personel_calisma_gecmisi
WHERE sgk_yapilan_firma LIKE '%?%';
