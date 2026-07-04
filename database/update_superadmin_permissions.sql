-- SQL script to mark permissions unique to Super Admin as superadmin = 1
UPDATE `permissions` SET `superadmin` = 1 WHERE `id` = 17;
UPDATE `permissions` SET `superadmin` = 1 WHERE `id` = 21;
UPDATE `permissions` SET `superadmin` = 1 WHERE `id` = 18;
UPDATE `permissions` SET `superadmin` = 1 WHERE `id` = 26;
UPDATE `permissions` SET `superadmin` = 1 WHERE `id` = 74;
UPDATE `permissions` SET `superadmin` = 1 WHERE `id` = 75;
UPDATE `permissions` SET `superadmin` = 1 WHERE `id` = 82;
UPDATE `permissions` SET `superadmin` = 1 WHERE `id` = 913;
UPDATE `permissions` SET `superadmin` = 1 WHERE `id` = 920;
UPDATE `permissions` SET `superadmin` = 1 WHERE `id` = 929;
