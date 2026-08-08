-- KM fotoğraf doğrulamasını daha güçlü görüntü anlayışına sahip GPT-5.4'e geçirir.
-- Yalnızca KM kontrolüne özel ayarı değiştirir; diğer AI iş yükleri etkilenmez.
START TRANSACTION;

UPDATE `settings`
SET `set_value` = 'gpt-5.4'
WHERE `set_name` = 'km_ai_model';

INSERT INTO `settings` (`user_id`, `firma_id`, `set_name`, `set_value`)
SELECT NULL, NULL, 'km_ai_model', 'gpt-5.4'
WHERE NOT EXISTS (
    SELECT 1
    FROM `settings`
    WHERE `set_name` = 'km_ai_model'
);

COMMIT;
