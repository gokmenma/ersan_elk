-- Bordro Parametreleri Tablosu
-- Ek ödeme ve kesinti türlerinin hesaplama kurallarını tanımlar

CREATE TABLE IF NOT EXISTS bordro_parametreleri (
    id INT AUTO_INCREMENT PRIMARY KEY,
    kod VARCHAR(50) NOT NULL UNIQUE,        -- Sistem kodu (yemek_yardimi, yol_yardimi, icra)
    etiket VARCHAR(100) NOT NULL,           -- Görünen isim ("Yemek Yardımı")
    kategori ENUM('gelir', 'kesinti') NOT NULL,
    
    -- Hesaplama Kuralları
    hesaplama_tipi ENUM('brut', 'net', 'kismi_muaf') NOT NULL DEFAULT 'net',
    -- brut: Brüt maaşa eklenir, tüm vergiler hesaplanır
    -- net: Net maaşa direkt eklenir (vergisiz)
    -- kismi_muaf: Belirli limite kadar vergisiz, üstü vergili (yemek yardımı gibi)
    
    gunluk_muaf_limit DECIMAL(12,2) DEFAULT 0,       -- Günlük vergiden muaf limit (örn: 158 TL/gün)
    aylik_muaf_limit DECIMAL(12,2) DEFAULT 0,        -- Aylık vergiden muaf limit (hesaplanabilir veya sabit)
    muaf_limit_tipi ENUM('gunluk', 'aylik', 'yok') DEFAULT 'yok',
    
    sgk_matrahi_dahil TINYINT(1) DEFAULT 0,          -- SGK matrahına dahil mi?
    gelir_vergisi_dahil TINYINT(1) DEFAULT 1,        -- Gelir vergisine tabi mi?
    damga_vergisi_dahil TINYINT(1) DEFAULT 0,        -- Damga vergisine tabi mi?
    
    -- Geçerlilik Dönemi
    gecerlilik_baslangic DATE NULL,         -- Hangi tarihten itibaren geçerli
    gecerlilik_bitis DATE NULL,             -- Hangi tarihe kadar geçerli (NULL = süresiz)
    
    -- Diğer
    varsayilan_tutar DECIMAL(12,2) DEFAULT 0,    -- Varsayılan tutar (opsiyonel)
    aciklama TEXT,
    sira INT DEFAULT 0,                          -- Sıralama
    aktif TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_kategori (kategori),
    INDEX idx_aktif (aktif),
    INDEX idx_gecerlilik (gecerlilik_baslangic, gecerlilik_bitis)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Gelir Vergisi Dilimleri Tablosu
-- Kümülatif gelir vergisi hesaplaması için dilimler

CREATE TABLE IF NOT EXISTS bordro_vergi_dilimleri (
    id INT AUTO_INCREMENT PRIMARY KEY,
    yil INT NOT NULL,                           -- Hangi yıl için geçerli
    dilim_no INT NOT NULL,                      -- Dilim sırası (1, 2, 3, ...)
    alt_limit DECIMAL(15,2) NOT NULL,           -- Dilimin alt sınırı
    ust_limit DECIMAL(15,2) NULL,               -- Dilimin üst sınırı (NULL = limit yok)
    vergi_orani DECIMAL(5,2) NOT NULL,          -- Vergi oranı (%)
    aciklama VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    UNIQUE KEY uk_yil_dilim (yil, dilim_no),
    INDEX idx_yil (yil)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Genel Bordro Ayarları Tablosu
-- Asgari ücret, SGK tavan/taban gibi genel parametreler

CREATE TABLE IF NOT EXISTS bordro_genel_ayarlar (
    id INT AUTO_INCREMENT PRIMARY KEY,
    parametre_kodu VARCHAR(50) NOT NULL,        -- asgari_ucret_brut, sgk_tavan, sgk_taban vb.
    parametre_adi VARCHAR(100) NOT NULL,
    deger DECIMAL(15,2) NOT NULL,
    gecerlilik_baslangic DATE NOT NULL,
    gecerlilik_bitis DATE NULL,
    aciklama TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_kod_tarih (parametre_kodu, gecerlilik_baslangic)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Varsayılan Parametreleri Ekle
INSERT INTO bordro_parametreleri (kod, etiket, kategori, hesaplama_tipi, gunluk_muaf_limit, muaf_limit_tipi, sgk_matrahi_dahil, gelir_vergisi_dahil, damga_vergisi_dahil, sira, aktif) VALUES
-- Gelirler
('prim', 'Prim', 'gelir', 'brut', 0, 'yok', 1, 1, 1, 1, 1),
('mesai', 'Fazla Mesai', 'gelir', 'brut', 0, 'yok', 1, 1, 1, 2, 1),
('ikramiye', 'İkramiye', 'gelir', 'brut', 0, 'yok', 1, 1, 1, 3, 1),
('yemek', 'Yemek Yardımı', 'gelir', 'kismi_muaf', 158.00, 'gunluk', 0, 1, 0, 4, 1),
('yol', 'Yol Yardımı', 'gelir', 'net', 0, 'yok', 0, 0, 0, 5, 1),
('diger_gelir', 'Diğer Gelir', 'gelir', 'net', 0, 'yok', 0, 0, 0, 99, 1),

-- Kesintiler
('avans', 'Avans', 'kesinti', 'net', 0, 'yok', 0, 0, 0, 1, 1),
('icra', 'İcra Kesintisi', 'kesinti', 'net', 0, 'yok', 0, 0, 0, 2, 1),
('nafaka', 'Nafaka', 'kesinti', 'net', 0, 'yok', 0, 0, 0, 3, 1),
('ceza', 'Ceza/Disiplin', 'kesinti', 'net', 0, 'yok', 0, 0, 0, 4, 1),
('diger_kesinti', 'Diğer Kesinti', 'kesinti', 'net', 0, 'yok', 0, 0, 0, 99, 1);


-- 2026 Yılı Gelir Vergisi Dilimleri
INSERT INTO bordro_vergi_dilimleri (yil, dilim_no, alt_limit, ust_limit, vergi_orani, aciklama) VALUES
(2026, 1, 0, 158000, 15.00, 'İlk 158.000 TL için %15'),
(2026, 2, 158000, 330000, 20.00, '158.000 - 330.000 TL arası %20'),
(2026, 3, 330000, 800000, 27.00, '330.000 - 800.000 TL arası %27'),
(2026, 4, 800000, 4300000, 35.00, '800.000 - 4.300.000 TL arası %35'),
(2026, 5, 4300000, NULL, 40.00, '4.300.000 TL üzeri %40');


-- 2026 Genel Bordro Ayarları
INSERT INTO bordro_genel_ayarlar (parametre_kodu, parametre_adi, deger, gecerlilik_baslangic, gecerlilik_bitis, aciklama) VALUES
('asgari_ucret_brut', 'Asgari Ücret (Brüt)', 33030.00, '2026-01-01', NULL, '2026 yılı brüt asgari ücret'),
('asgari_ucret_net', 'Asgari Ücret (Net)', 28075.00, '2026-01-01', NULL, '2026 yılı net asgari ücret'),
('sgk_isci_orani', 'SGK İşçi Payı Oranı (%)', 14.00, '2026-01-01', NULL, 'SGK işçi kesinti oranı'),
('issizlik_isci_orani', 'İşsizlik Sigortası İşçi Payı (%)', 1.00, '2026-01-01', NULL, 'İşsizlik işçi kesinti oranı'),
('sgk_isveren_orani', 'SGK İşveren Payı Oranı (%)', 20.50, '2026-01-01', NULL, 'SGK işveren kesinti oranı'),
('issizlik_isveren_orani', 'İşsizlik Sigortası İşveren Payı (%)', 2.00, '2026-01-01', NULL, 'İşsizlik işveren kesinti oranı'),
('damga_vergisi_orani', 'Damga Vergisi Oranı (%)', 0.759, '2026-01-01', NULL, 'Damga vergisi oranı'),
('yemek_yardimi_gunluk_istisna', 'Yemek Yardımı Günlük İstisna', 158.00, '2026-01-01', NULL, 'Günlük yemek yardımı gelir vergisi istisnası'),
('calisma_gunu_sayisi', 'Aylık Çalışma Günü Sayısı', 26.00, '2026-01-01', NULL, 'Aylık ortalama çalışma günü');
