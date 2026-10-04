-- Kalıcı EDM arka plan aktarımı ve kaldığı yerden devam etme.
CREATE TABLE IF NOT EXISTS efatura_sync_jobs (
    id CHAR(32) NOT NULL PRIMARY KEY,
    firm_id INT NOT NULL,
    user_id INT NOT NULL,
    state_json LONGTEXT NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'queued',
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    is_active TINYINT NOT NULL DEFAULT 1,
    deleted_at DATETIME NULL,
    KEY idx_sync_owner (firm_id, user_id, created_at),
    KEY idx_sync_status (status, updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
