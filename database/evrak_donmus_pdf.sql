ALTER TABLE evrak_takip
    ADD COLUMN IF NOT EXISTS donmus_pdf_yolu VARCHAR(255) NULL AFTER pades_pdf_sha256,
    ADD COLUMN IF NOT EXISTS donmus_pdf_sha256 CHAR(64) NULL AFTER donmus_pdf_yolu,
    ADD COLUMN IF NOT EXISTS donmus_pdf_tarihi DATETIME NULL AFTER donmus_pdf_sha256;
