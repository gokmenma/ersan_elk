-- Sistem aktiviteleri birleşik server-side listesi için sorgu indeksleri.
-- MariaDB 10.4+ üzerinde tekrar çalıştırılabilir.

ALTER TABLE system_logs
    ADD INDEX IF NOT EXISTS idx_system_logs_firma_created (firma_id, created_at),
    ADD INDEX IF NOT EXISTS idx_system_logs_firma_level_created (firma_id, level, created_at),
    ADD INDEX IF NOT EXISTS idx_system_logs_firma_action_created (firma_id, action_type, created_at);

ALTER TABLE personel_giris_loglari
    ADD INDEX IF NOT EXISTS idx_personel_giris_personel_tarih (personel_id, giris_tarihi);

ALTER TABLE ai_agent_logs
    ADD INDEX IF NOT EXISTS idx_ai_agent_firma_created (firma_id, created_at);
