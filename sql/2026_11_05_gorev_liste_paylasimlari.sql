CREATE TABLE IF NOT EXISTS gorev_liste_paylasimlari (
    id INT AUTO_INCREMENT PRIMARY KEY,
    liste_id INT NOT NULL,
    user_id INT NOT NULL,
    yetki ENUM('goruntule','duzenle') NOT NULL DEFAULT 'duzenle',
    paylasan_id INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_liste_user (liste_id, user_id),
    INDEX idx_user_id (user_id),
    CONSTRAINT fk_gorev_paylasim_liste FOREIGN KEY (liste_id) REFERENCES gorev_listeleri(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permission_policies
    (scope, resource, action, http_method, permission_id, superadmin_only, authenticated_only)
SELECT 'api', 'gorevler/api', actions.action, '*', p.id, 0, 0
FROM (
    SELECT 'get-liste-paylasim' AS action
    UNION ALL SELECT 'save-liste-paylasim'
) actions
INNER JOIN permissions p
    ON p.is_active = 1
   AND (p.permission_key = 'gorevler' OR p.auth_name = 'gorevler')
ON DUPLICATE KEY UPDATE
    permission_id = VALUES(permission_id),
    superadmin_only = 0,
    authenticated_only = 0,
    is_active = 1;
