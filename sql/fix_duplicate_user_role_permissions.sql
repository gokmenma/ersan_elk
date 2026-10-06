-- Rol-yetki bağlantılarındaki mükerrer kayıtları temizler ve tekrar oluşmasını engeller.
-- Her (role_id, permission_id) eşleşmesinde en eski (en küçük id) kayıt korunur.

DELETE duplicate_urp
FROM user_role_permissions AS duplicate_urp
INNER JOIN user_role_permissions AS original_urp
    ON original_urp.role_id = duplicate_urp.role_id
   AND original_urp.permission_id = duplicate_urp.permission_id
   AND original_urp.id < duplicate_urp.id;

-- Script yeniden çalıştırıldığında indeks zaten varsa hata vermemesi için koşullu DDL.
SET @unique_index_exists = (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'user_role_permissions'
      AND index_name = 'uq_user_role_permission'
);

SET @unique_index_sql = IF(
    @unique_index_exists = 0,
    'ALTER TABLE user_role_permissions ADD UNIQUE KEY uq_user_role_permission (role_id, permission_id)',
    'SELECT 1'
);

PREPARE unique_index_statement FROM @unique_index_sql;
EXECUTE unique_index_statement;
DEALLOCATE PREPARE unique_index_statement;
