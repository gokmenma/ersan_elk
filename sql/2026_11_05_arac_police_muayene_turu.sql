ALTER TABLE arac_police_dosyalari
    MODIFY COLUMN police_turu ENUM('sigorta','kasko','muayene') NOT NULL;

INSERT INTO permission_policies (
    scope, resource, action, http_method, access_mode,
    permission_id, superadmin_only, authenticated_only, is_active, created_at, updated_at
)
SELECT 'api', 'arac-takip/api', 'arac-ruhsat-ai-analiz', '*', 'write',
       p.id, 0, 0, 1, NOW(), NOW()
FROM permissions p
WHERE p.permission_key = 'arac_takip_yonetim'
AND NOT EXISTS (
    SELECT 1 FROM permission_policies pp
    WHERE pp.scope = 'api'
    AND pp.resource = 'arac-takip/api'
    AND pp.action = 'arac-ruhsat-ai-analiz'
);
