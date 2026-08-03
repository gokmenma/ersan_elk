-- Fix Super Admin role flag and remove Super Admin only permissions from non-superadmin roles

-- 1. Fix user_roles superadmin flag: Only superadmin role (id = 1, role_type = 'superadmin') should have superadmin = 1
UPDATE `user_roles` SET `superadmin` = 0 WHERE `role_type` != 'superadmin' AND `role_name` != 'Süper Admin';

-- 2. Remove superadmin-only permissions (permissions with superadmin = 1) from non-superadmin roles in user_role_permissions
DELETE urp FROM `user_role_permissions` urp
JOIN `permissions` p ON p.id = urp.permission_id
JOIN `user_roles` ur ON ur.id = urp.role_id
WHERE p.superadmin = 1 AND ur.role_type != 'superadmin' AND ur.role_name != 'Süper Admin';
