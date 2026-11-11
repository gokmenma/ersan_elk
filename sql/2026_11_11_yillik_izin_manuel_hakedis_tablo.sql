-- Yıllık izin manuel hakediş kayıtları (modal veya Excel ile eklenen, kıdemden hesaplanan hakedişe ilave günler).
-- Silme işlemi soft delete'tir (deleted_at, is_active = 0). Betik tekrar çalıştırılabilir.

CREATE TABLE IF NOT EXISTS personel_yillik_izin_manuel_hakedis (
    id INT(11) NOT NULL AUTO_INCREMENT,
    firma_id INT(11) NOT NULL,
    personel_id INT(11) NOT NULL,
    hakedis_tarihi DATE NOT NULL,
    gun DECIMAL(5,1) NOT NULL,
    aciklama VARCHAR(255) NULL DEFAULT NULL,
    kaynak VARCHAR(10) NOT NULL DEFAULT 'manuel',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by INT(11) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL DEFAULT NULL,
    deleted_by INT(11) NULL DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_manuel_hakedis_personel (personel_id, is_active, deleted_at),
    KEY idx_manuel_hakedis_firma (firma_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_turkish_ci;
