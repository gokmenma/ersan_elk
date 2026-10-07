-- Eski programdan kalan 'Finans Yönetimi' ana menüsü ve alt menülerinin (Gelir-Gider Listesi, Kasa Listesi, Online Hesap Hareketleri) pasife alınması / kapatılması

START TRANSACTION;

-- 1. Menüleri soft delete ve pasife alma
UPDATE menus 
SET is_active = 0, 
    is_menu = 0, 
    deleted_at = NOW() 
WHERE id IN (12, 13, 14, 46) 
   OR parent_id = 12;

-- 2. Eski izinleri pasife alma
UPDATE permissions 
SET is_active = 0 
WHERE id IN (12, 13, 14, 46);

-- 3. Eski sayfa ve API yetki politikalarını pasife alma
UPDATE permission_policies 
SET is_active = 0 
WHERE permission_id IN (12, 13, 14, 46)
   OR resource IN ('kasa/list', 'kasa/duzenle', 'kasa/api', 'gelir-gider/online-hesap-hareketleri');

COMMIT;
