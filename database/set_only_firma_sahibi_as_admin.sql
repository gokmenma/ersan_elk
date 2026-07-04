-- SQL Script to set only Firma Sahibi as admin, and other manager/officer roles as user

UPDATE `user_roles` SET `role_type` = 'user' WHERE `id` IN (11, 12, 13, 14, 15, 17);
