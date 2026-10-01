-- Apply before deploying the new PWA assets. Receipts must be retained for retries.
CREATE TABLE IF NOT EXISTS pwa_transfer_receipts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    firma_id INT NOT NULL,
    personel_id INT NOT NULL,
    operation_key VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    action_name VARCHAR(64) NOT NULL,
    response_json LONGTEXT NULL,
    completed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_pwa_operation (firma_id, personel_id, operation_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
