SET @firma_id := 1;

START TRANSACTION;

DELETE FROM kesme_acma_islem_foto WHERE firma_id = @firma_id;
DELETE FROM kesme_acma_islem WHERE firma_id = @firma_id;
DELETE FROM aparat_sayim_detay WHERE firma_id = @firma_id;
DELETE FROM aparat_sayim WHERE firma_id = @firma_id;
DELETE FROM aparat_transfer WHERE firma_id = @firma_id;
DELETE FROM aparat_hareket WHERE firma_id = @firma_id;
DELETE FROM aparat_stok WHERE firma_id = @firma_id;

COMMIT;
