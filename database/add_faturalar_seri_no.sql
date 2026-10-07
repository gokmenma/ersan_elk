-- Fatura oluşturma ekranında seçilen EDM serisini taslakla birlikte saklar.
ALTER TABLE `faturalar`
    ADD COLUMN `seri_no` VARCHAR(3) NULL AFTER `fatura_no`;
