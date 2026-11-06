-- İşe alım başvuru bildirimleri: kullanıcı bazlı e-posta tercihi ve yönetici onaylı (varsayılan kapalı) uygulama/push bildirimi.
-- Tekrar çalıştırılabilir. Yeni alanların hepsi varsayılan olarak kapalıdır; Süper Admin kullanıcı düzenleme ekranından kullanıcı bazında açar.

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS mail_ise_alim_basvuru ENUM('Evet','Hayır') NOT NULL DEFAULT 'Hayır' COMMENT 'Yeni işe alım başvurusunda e-posta al';

ALTER TABLE user_notification_preferences
    ADD COLUMN IF NOT EXISTS admin_disabled TINYINT(1) NOT NULL DEFAULT 0;

ALTER TABLE user_notification_preferences
    ADD COLUMN IF NOT EXISTS admin_granted TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Varsayılan kapalı bildirim türlerinde yönetici izni';
