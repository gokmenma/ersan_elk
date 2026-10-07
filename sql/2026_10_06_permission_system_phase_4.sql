-- Yetki Sistemi Dönüşümü / Aşama 4
-- Kullanıcı-rol ilişkisini tarihçeli bağlantı tablosuna taşır.

CREATE TABLE IF NOT EXISTS user_role_assignments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT NOT NULL,
    role_id INT NOT NULL,
    assigned_by INT NULL,
    starts_at DATETIME NULL,
    ends_at DATETIME NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    reason VARCHAR(500) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_ura_user_active_dates (user_id, is_active, starts_at, ends_at, deleted_at),
    KEY idx_ura_role_active (role_id, is_active, deleted_at),
    KEY idx_ura_assigned_by (assigned_by),
    CONSTRAINT fk_ura_user FOREIGN KEY (user_id) REFERENCES users(id),
    CONSTRAINT fk_ura_role FOREIGN KEY (role_id) REFERENCES user_roles(id),
    CONSTRAINT fk_ura_assigned_by FOREIGN KEY (assigned_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO user_role_assignments
    (user_id, role_id, assigned_by, starts_at, ends_at, is_active, reason, created_at, updated_at)
SELECT u.id, ur.id, NULL, NOW(), NULL, 1, 'users.roles alanından Aşama 4 geçişi', NOW(), NOW()
FROM users u
INNER JOIN user_roles ur ON FIND_IN_SET(CAST(ur.id AS CHAR), REPLACE(u.roles, ' ', '')) > 0
LEFT JOIN user_role_assignments ura
    ON ura.user_id = u.id AND ura.role_id = ur.id
   AND ura.is_active = 1 AND ura.deleted_at IS NULL
WHERE ura.id IS NULL;

SELECT u.id AS user_id, u.user_name, ur.id AS role_id, ur.role_name
FROM users u
INNER JOIN user_roles ur ON FIND_IN_SET(CAST(ur.id AS CHAR), REPLACE(u.roles, ' ', '')) > 0
LEFT JOIN user_role_assignments ura
    ON ura.user_id = u.id AND ura.role_id = ur.id
   AND ura.is_active = 1 AND ura.deleted_at IS NULL
WHERE ura.id IS NULL
ORDER BY u.id, ur.id;
