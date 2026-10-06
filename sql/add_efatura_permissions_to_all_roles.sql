-- E-Fatura modülü tüm yetkilerinin (Dashboard dahil) rollere tanımlanması
INSERT IGNORE INTO user_role_permissions (role_id, permission_id)
SELECT r.id, p.id 
FROM user_roles r
CROSS JOIN permissions p
WHERE r.id != 21 AND p.auth_name LIKE 'efatura/%';
