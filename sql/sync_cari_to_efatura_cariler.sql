-- ============================================================================
-- E-Fatura: cari tablosundaki mevcut carileri efatura_cariler tablosuna aktarma
-- Tarih: 2026-10-05
-- ============================================================================

INSERT INTO efatura_cariler (
    firm_id,
    cari_kodu,
    unvan,
    kisa_ad,
    vkn_tckn,
    vergi_dairesi,
    alici_turu,
    belge_turu,
    posta_kutusu,
    telefon,
    eposta,
    web_sitesi,
    ticaret_sicil_no,
    mersis_no,
    ulke,
    il,
    ilce,
    posta_kodu,
    adres,
    notlar,
    is_active,
    created_by,
    created_at
)
SELECT 
    1 AS firm_id,
    CONCAT('CAR-', LPAD(c.id, 4, '0')) AS cari_kodu,
    TRIM(IF(c.firma IS NOT NULL AND TRIM(c.firma) != '', c.firma, c.CariAdi)) AS unvan,
    TRIM(c.CariAdi) AS kisa_ad,
    COALESCE(c.vkn_tckn, '') AS vkn_tckn,
    COALESCE(c.vergi_dairesi, '') AS vergi_dairesi,
    COALESCE(c.alici_turu, 'KURUMSAL') AS alici_turu,
    COALESCE(c.belge_turu, 'OTOMATIK') AS belge_turu,
    COALESCE(c.posta_kutusu, '') AS posta_kutusu,
    COALESCE(c.Telefon, '') AS telefon,
    COALESCE(c.Email, '') AS eposta,
    COALESCE(c.web_sitesi, '') AS web_sitesi,
    COALESCE(c.ticaret_sicil_no, '') AS ticaret_sicil_no,
    COALESCE(c.mersis_no, '') AS mersis_no,
    IF(c.ulke IS NOT NULL AND TRIM(c.ulke) != '', c.ulke, 'Türkiye') AS ulke,
    COALESCE(c.il, '') AS il,
    COALESCE(c.ilce, '') AS ilce,
    COALESCE(c.posta_kodu, '') AS posta_kodu,
    COALESCE(c.Adres, '') AS adres,
    COALESCE(c.notlar, '') AS notlar,
    COALESCE(c.Aktif, 1) AS is_active,
    1 AS created_by,
    COALESCE(c.kayit_tarihi, NOW()) AS created_at
FROM cari c
WHERE c.silinme_tarihi IS NULL
  AND NOT EXISTS (
      SELECT 1 FROM efatura_cariler ec 
      WHERE ec.firm_id = 1 
        AND (
            (ec.unvan = IF(c.firma IS NOT NULL AND TRIM(c.firma) != '', TRIM(c.firma), TRIM(c.CariAdi)))
            OR (ec.kisa_ad = TRIM(c.CariAdi) AND TRIM(c.CariAdi) != '')
            OR (ec.vkn_tckn != '' AND ec.vkn_tckn = c.vkn_tckn)
        )
        AND ec.deleted_at IS NULL
  );
