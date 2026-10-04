-- EDM/UBL kod listeleri eski ENUM listesinden daha fazla profil ve fatura tipi içerir.
-- Mevcut değerler korunur; kod doğrulaması InvoiceValidationService üzerinden sürer.
-- efatura_tamamlama.sql içindeki aynı alan güncellemesinin bağımsız, tekrar çalıştırılabilir hali.
ALTER TABLE faturalar
    MODIFY fatura_profili VARCHAR(40) NOT NULL DEFAULT 'TICARIFATURA',
    MODIFY fatura_tipi VARCHAR(40) NOT NULL DEFAULT 'SATIS';
