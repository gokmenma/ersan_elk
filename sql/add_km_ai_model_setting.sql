-- KM fotoğraf doğrulamasını genel amaçlı AI modelinden ayırır.
-- Firma özelinde km_ai_model tanımlanmadığında bu global değer kullanılır.
INSERT INTO `settings` (`user_id`, `firma_id`, `set_name`, `set_value`)
SELECT NULL, NULL, 'km_ai_model', 'gpt-4o'
WHERE NOT EXISTS (
    SELECT 1
    FROM `settings`
    WHERE `set_name` = 'km_ai_model'
      AND `user_id` IS NULL
      AND `firma_id` IS NULL
);
