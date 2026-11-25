START TRANSACTION;

SET @demirbas_menu_id = (SELECT id FROM menus WHERE menu_link = 'demirbas/list' AND parent_id = 0 LIMIT 1);
SET @aparat_group_name = (SELECT group_name FROM menus WHERE id = @demirbas_menu_id);
SET @aparat_group_order = (SELECT group_order FROM menus WHERE id = @demirbas_menu_id);

INSERT INTO menus
    (menu_name, page_description, parent_id, group_name, group_order, menu_link, permission_id, menu_icon, menu_order, is_active, is_menu, is_authorized)
SELECT
    'Aparat',
    'Aparat takip ve dashboard sayfalarını gruplayan alt menü',
    @demirbas_menu_id,
    @aparat_group_name,
    @aparat_group_order,
    '',
    NULL,
    'package',
    55,
    1,
    1,
    1
FROM DUAL
WHERE @demirbas_menu_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1 FROM menus WHERE parent_id = @demirbas_menu_id AND menu_name = 'Aparat' AND (menu_link = '' OR menu_link IS NULL)
  );

SET @aparat_alt_menu_id = (
    SELECT id FROM menus
    WHERE parent_id = @demirbas_menu_id AND menu_name = 'Aparat' AND (menu_link = '' OR menu_link IS NULL)
    LIMIT 1
);

UPDATE menus
SET parent_id = @aparat_alt_menu_id, menu_order = 1
WHERE menu_link = 'aparat-takip/list' AND @aparat_alt_menu_id IS NOT NULL;

UPDATE menus
SET parent_id = @aparat_alt_menu_id, menu_order = 2
WHERE menu_link = 'aparat-takip/dashboard' AND @aparat_alt_menu_id IS NOT NULL;

COMMIT;
