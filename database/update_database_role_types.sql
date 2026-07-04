-- SQL Script to correct user_roles role_type and volkaner role value

UPDATE `user_roles` SET `role_type` = 'superadmin' WHERE `id` = 1;
UPDATE `user_roles` SET `role_type` = 'admin' WHERE `id` IN (2, 11, 12, 13, 14, 15, 17);
UPDATE `user_roles` SET `role_type` = 'user' WHERE `id` = 16;

-- Update user volkaner to admin role
UPDATE `users` SET `role` = 'admin' WHERE `user_name` = 'volkaner';
