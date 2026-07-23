-- =====================================================
-- YAPAY ZEKA İŞ AJANI (AI AGENT) VERİTABANI TABLOLARI
-- =====================================================

CREATE TABLE IF NOT EXISTS `ai_agent_logs` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `firma_id` INT(11) NOT NULL,
  `user_id` INT(11) NOT NULL,
  `module` VARCHAR(50) NOT NULL DEFAULT 'arac-takip',
  `prompt` TEXT NOT NULL,
  `response` MEDIUMTEXT DEFAULT NULL,
  `prompt_tokens` INT(11) DEFAULT 0,
  `completion_tokens` INT(11) DEFAULT 0,
  `total_tokens` INT(11) DEFAULT 0,
  `cost_estimate` DECIMAL(10, 6) DEFAULT 0.000000,
  `execution_time_ms` INT(11) DEFAULT 0,
  `model_used` VARCHAR(50) DEFAULT NULL,
  `status` ENUM('success', 'error') DEFAULT 'success',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_firma_module` (`firma_id`, `module`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ai_agent_cache` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `firma_id` INT(11) NOT NULL,
  `module` VARCHAR(50) NOT NULL,
  `cache_key` VARCHAR(64) NOT NULL,
  `response` MEDIUMTEXT NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_firma_module_key` (`firma_id`, `module`, `cache_key`),
  KEY `idx_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
