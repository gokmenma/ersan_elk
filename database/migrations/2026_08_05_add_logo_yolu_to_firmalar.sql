ALTER TABLE `firmalar`
    ADD COLUMN `logo_yolu` VARCHAR(500) NULL AFTER `firma_unvan`;

UPDATE `firmalar` f
INNER JOIN `settings` s ON s.firma_id = f.id
SET f.logo_yolu = s.set_value
WHERE s.set_name = 'evrak_logo_yolu'
  AND s.user_id IS NULL
  AND s.set_value IS NOT NULL
  AND s.set_value <> '';
