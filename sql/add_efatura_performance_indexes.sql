-- E-Fatura & E-Arşiv Modülü Yüksek Performans İndeksleri (Server-Side Optimization)
-- Ersan Elektrik Projesi

-- 1. Faturalar Listeleme ve İstatistikler için Kapsayıcı (Covering) Bileşik İndeks
CREATE INDEX IF NOT EXISTS idx_faturalar_list_perf 
ON faturalar (firm_id, deleted_at, yon, entegrator_durum_kodu, fatura_tarihi);

-- 2. Alıcı Unvan Arama İndeksi
CREATE INDEX IF NOT EXISTS idx_faturalar_alici_unvan 
ON faturalar (firm_id, alici_unvan(100));

-- 3. Alıcı VKN / TCKN Arama İndeksi
CREATE INDEX IF NOT EXISTS idx_faturalar_alici_vkn 
ON faturalar (firm_id, alici_vkn_tckn);

-- 4. Fatura Satırları için Yabancı Anahtar ve Sıralama İndeksi
CREATE INDEX IF NOT EXISTS idx_fatura_satirlari_fatura_sira 
ON fatura_satirlari (fatura_id, sira_no);
