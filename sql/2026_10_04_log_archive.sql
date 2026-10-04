-- Bir kez çalıştırılır. Kayıtlar/ID'ler korunur; kalıcı silme yapılmaz.
ALTER TABLE system_logs
    ADD COLUMN archived_at DATETIME NULL DEFAULT NULL,
    ADD INDEX idx_log_archive_date (archived_at, created_at, id),
    ADD INDEX idx_log_firma_archive_date (firma_id, archived_at, created_at);
ALTER TABLE personel_giris_loglari
    ADD COLUMN archived_at DATETIME NULL DEFAULT NULL,
    ADD INDEX idx_pg_archive_date (archived_at, giris_tarihi, id),
    ADD INDEX idx_pg_personel_archive_date (personel_id, archived_at, giris_tarihi);
ALTER TABLE ai_agent_logs
    ADD COLUMN archived_at DATETIME NULL DEFAULT NULL,
    ADD INDEX idx_ai_archive_date (archived_at, created_at, id),
    ADD INDEX idx_ai_firma_archive_date (firma_id, archived_at, created_at);
