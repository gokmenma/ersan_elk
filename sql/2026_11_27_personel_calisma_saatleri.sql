CREATE TABLE IF NOT EXISTS personel_calisma_saatleri (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    personel_id INT NOT NULL,
    gorev_baslama TIME NULL,
    gorev_bitis TIME NULL,
    yemek_baslangic TIME NULL,
    yemek_bitis TIME NULL,
    mola1_baslangic TIME NULL,
    mola1_bitis TIME NULL,
    mola2_baslangic TIME NULL,
    mola2_bitis TIME NULL,
    diger_dusulecek_dk SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    net_calisma_dk SMALLINT UNSIGNED NULL,
    olusturma_tarihi DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    guncelleme_tarihi DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    silinme_tarihi DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_pcs_personel (personel_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permission_policies
    (scope, resource, action, http_method, permission_id, superadmin_only, authenticated_only)
SELECT 'api', 'personel-takip/api', policies.action, '*', p.id, 0, 0
FROM (
    SELECT 'getPersonelCalismaSaatleri' AS action, 'personel_takip_deparmana_gore_ise_baslama_belirleme' AS permission_key
    UNION ALL SELECT 'savePersonelCalismaSaatleri', 'personel_takip_deparmana_gore_ise_baslama_belirleme'
) policies
INNER JOIN permissions p
    ON p.is_active = 1
   AND (p.permission_key = policies.permission_key OR p.auth_name = policies.permission_key)
ON DUPLICATE KEY UPDATE
    permission_id = VALUES(permission_id), superadmin_only = 0,
    authenticated_only = 0, is_active = 1;
