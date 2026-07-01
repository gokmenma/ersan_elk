-- TC Kimlik No şifrelemesi için gerekli kolon değişiklikleri

ALTER TABLE personel
    MODIFY COLUMN tc_kimlik_no VARCHAR(512) DEFAULT NULL,
    ADD COLUMN tc_hash VARCHAR(64) DEFAULT NULL AFTER tc_kimlik_no,
    ADD INDEX idx_tc_hash (tc_hash);
