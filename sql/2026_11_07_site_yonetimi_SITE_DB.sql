-- Hedef veritabanı: kurumsal SİTE içerik veritabanı (yerel: ersan_site, canlı: ersantrc_site). Personel veritabanında çalıştırmayın.
-- Önce tablolar, en sonda isteğe bağlı örnek veriler (ornek_veri bölümleri atlanabilir).
-- ===== 2026_11_07_site_db_hizmetler.sql =====
CREATE TABLE IF NOT EXISTS site_hizmetler (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    slug VARCHAR(160) NOT NULL,
    baslik VARCHAR(200) NOT NULL,
    kisa_aciklama VARCHAR(300) NULL,
    aciklama TEXT NULL,
    ozellikler TEXT NULL,
    adimlar TEXT NULL,
    gorsel VARCHAR(255) NULL,
    sira INT NOT NULL DEFAULT 0,
    durum ENUM('taslak','yayinda') NOT NULL DEFAULT 'taslak',
    olusturan_id INT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_site_hizmet_slug (slug),
    KEY idx_site_hizmet_liste (is_active, deleted_at, durum, sira)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

-- ===== 2026_11_07_site_db_projeler.sql =====
CREATE TABLE IF NOT EXISTS site_projeler (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    slug VARCHAR(160) NOT NULL,
    baslik VARCHAR(200) NOT NULL,
    kategori VARCHAR(100) NULL,
    musteri VARCHAR(150) NULL,
    konum VARCHAR(150) NULL,
    proje_yili SMALLINT UNSIGNED NULL,
    kisa_aciklama VARCHAR(300) NULL,
    aciklama TEXT NULL,
    gorsel VARCHAR(255) NULL,
    sira INT NOT NULL DEFAULT 0,
    durum ENUM('taslak','yayinda') NOT NULL DEFAULT 'taslak',
    olusturan_id INT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_site_proje_slug (slug),
    KEY idx_site_proje_liste (is_active, deleted_at, durum, sira)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

-- ===== 2026_11_07_site_db_referanslar.sql =====
CREATE TABLE IF NOT EXISTS site_referanslar (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    kurum VARCHAR(150) NOT NULL,
    kisi VARCHAR(150) NULL,
    unvan VARCHAR(150) NULL,
    yorum TEXT NOT NULL,
    logo VARCHAR(255) NULL,
    sira INT NOT NULL DEFAULT 0,
    durum ENUM('taslak','yayinda') NOT NULL DEFAULT 'taslak',
    olusturan_id INT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_site_referanslar_liste (is_active, deleted_at, durum, sira)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

-- ===== 2026_11_07_site_db_sss.sql =====
CREATE TABLE IF NOT EXISTS site_sss (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    soru VARCHAR(300) NOT NULL,
    cevap TEXT NOT NULL,
    sira INT NOT NULL DEFAULT 0,
    durum ENUM('taslak','yayinda') NOT NULL DEFAULT 'taslak',
    olusturan_id INT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_site_sss_liste (is_active, deleted_at, durum, sira)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

-- ===== 2026_11_07_site_db_istatistikler.sql =====
CREATE TABLE IF NOT EXISTS site_istatistikler (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    etiket VARCHAR(100) NOT NULL,
    deger INT UNSIGNED NOT NULL DEFAULT 0,
    sonek VARCHAR(10) NULL,
    sira INT NOT NULL DEFAULT 0,
    durum ENUM('taslak','yayinda') NOT NULL DEFAULT 'taslak',
    olusturan_id INT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_site_istatistikler_liste (is_active, deleted_at, durum, sira)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

-- ===== 2026_11_07_site_db_mesajlar.sql =====
CREATE TABLE IF NOT EXISTS site_mesajlar (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    ad_soyad VARCHAR(150) NOT NULL,
    telefon VARCHAR(30) NULL,
    email VARCHAR(190) NULL,
    konu VARCHAR(200) NULL,
    mesaj TEXT NOT NULL,
    ip_adresi VARCHAR(45) NULL,
    kvkk_onay_tarihi DATETIME NOT NULL,
    durum ENUM('yeni','okundu','cevaplandi') NOT NULL DEFAULT 'yeni',
    okuyan_id INT NULL,
    okunma_tarihi DATETIME NULL,
    yonetici_notu TEXT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_site_mesaj_liste (is_active, deleted_at, durum, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

CREATE TABLE IF NOT EXISTS site_mesaj_denemeleri (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    ip_adresi VARCHAR(45) NOT NULL,
    sonuc VARCHAR(20) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_site_mesaj_deneme_ip (ip_adresi, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

-- ===== 2026_11_07_site_db_ayarlar.sql =====
CREATE TABLE IF NOT EXISTS site_ayarlar (
    anahtar VARCHAR(100) NOT NULL,
    deger TEXT NULL,
    guncelleyen_id INT NULL,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (anahtar)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

-- ===== 2026_11_07_site_db_hizmetler_ornek_veri.sql =====
INSERT INTO site_hizmetler (slug, baslik, kisa_aciklama, aciklama, ozellikler, adimlar, gorsel, sira, durum)
SELECT d.slug, d.baslik, d.kisa_aciklama, d.aciklama, d.ozellikler, d.adimlar, d.gorsel, d.sira, 'yayinda'
FROM (
    SELECT 'elektrik-sayac-okuma' AS slug, 'Elektrik Sayaç Okuma' AS baslik,
        'Endeks okuma ve kayıt hizmetleri, güvenli ve hatasız veri akışıyla.' AS kisa_aciklama,
        'Elektrik dağıtım şirketleri için periyodik endeks okuma hizmeti veriyoruz. Okumalar mobil uygulama ile fotoğraf ve konum bilgisiyle kaydedilir, hatalı veya şüpheli okumalar anında işaretlenir.' AS aciklama,
        'Fotoğraflı ve konumlu endeks kaydı\nAnormal tüketim işaretleme\nRota bazlı planlama\nGünlük ilerleme raporu' AS ozellikler,
        'Rota ve abone listesinin alınması\nSaha ekibinin görevlendirilmesi\nMobil uygulama ile okuma\nDoğrulama ve teslim' AS adimlar,
        'uploads/site/hizmetler/svc-elektrik.jpg' AS gorsel, 1 AS sira
    UNION ALL SELECT 'su-sayac-okuma', 'Su Sayaç Okuma',
        'Kentsel ve kırsal abone noktalarında periyodik su sayacı okuma.',
        'Kentsel ve kırsal bölgelerde su sayaçlarının periyodik okunmasını sağlıyoruz. Ulaşılamayan sayaçlar nedenleriyle birlikte raporlanır.',
        'Ulaşılamayan sayaç nedeni kaydı\nFotoğraflı doğrulama\nBölge bazlı raporlama\nHızlı veri aktarımı',
        'Bölge ve takvim planı\nEkip ataması\nSahada okuma\nRaporlama', 'uploads/site/hizmetler/svc-su.jpg', 2
    UNION ALL SELECT 'dogalgaz-sayac-okuma', 'Doğalgaz Sayaç Okuma',
        'Doğalgaz endeks okuma ve abone verisi doğrulama hizmetleri.',
        'Doğalgaz endeks okuma ve abone verisi doğrulama işlerini, iş güvenliği standartlarına uygun şekilde yürütüyoruz.',
        'Endeks ve seri no doğrulama\nİş güvenliği prosedürleri\nDetaylı okuma raporu\nHatalı kayıt düzeltme desteği',
        'Abone listesi kontrolü\nSaha ziyareti\nOkuma ve doğrulama\nVeri teslimi', 'uploads/site/hizmetler/svc-gaz.jpg', 3
    UNION ALL SELECT 'kesme-acma-ve-degisim', 'Kesme / Açma ve Değişim',
        'Enerji kesme-açma ile planlı sayaç değişim operasyonları.',
        'Enerji kesme-açma operasyonlarını ve planlı sayaç değişim projelerini, tam süreç takibi ve kayıt altına alınmış teslimle yürütüyoruz.',
        'Anlık iş durumu takibi\nFotoğraflı işlem kaydı\nPlanlı toplu değişim\nStandartlara uygun montaj',
        'İş emrinin alınması\nEkip ve malzeme planı\nSaha uygulaması\nKapanış raporu', 'uploads/site/hizmetler/svc-teknik.jpg', 4
) d
WHERE NOT EXISTS (SELECT 1 FROM site_hizmetler s WHERE s.slug = d.slug);

-- ===== 2026_11_07_site_db_projeler_ornek_veri.sql =====
INSERT INTO site_projeler (slug, baslik, kategori, konum, kisa_aciklama, aciklama, gorsel, sira, durum)
SELECT d.slug, d.baslik, d.kategori, d.konum, d.kisa_aciklama, d.aciklama, d.gorsel, d.sira, 'yayinda'
FROM (
    SELECT 'iskenderun-endeks-okuma' AS slug, 'İskenderun Endeks Okuma' AS baslik, 'Sayaç Okuma' AS kategori, 'İskenderun / Hatay' AS konum,
        'Geniş bir abone bölgesinde periyodik elektrik endeks okuma işi.' AS kisa_aciklama,
        'Geniş bir abone bölgesinde periyodik elektrik endeks okuma çalışması yürütüldü. Okumalar mobil uygulama ile fotoğraf ve konum bilgisiyle kaydedildi.' AS aciklama,
        'uploads/site/projeler/hero-1.jpg' AS gorsel, 1 AS sira
    UNION ALL SELECT 'hatay-kesme-acma-operasyonu', 'Hatay Kesme-Açma Operasyonu', 'Kesme / Açma', 'Hatay', 'Planlı enerji kesme ve açma operasyonlarının sahadan yönetimi.', 'Planlı enerji kesme ve açma operasyonları saha ekipleriyle yönetildi, her işlem kayıt altına alındı.', 'uploads/site/projeler/hero-2.jpg', 2
    UNION ALL SELECT 'toplu-sayac-degisimi', 'Toplu Sayaç Değişimi', 'Sayaç Değişimi', 'Hatay', 'Merkezi izleme ile desteklenen toplu sayaç değişim projesi.', 'Merkezi izleme ile desteklenen toplu sayaç değişim projesi tamamlandı.', 'uploads/site/projeler/hero-3.jpg', 3
    UNION ALL SELECT 'su-sayaci-okuma-projesi', 'Su Sayacı Okuma Projesi', 'Sayaç Okuma', 'Hatay', 'Kentsel bölgelerde su sayaçlarının düzenli okunması.', 'Kentsel bölgelerde su sayaçlarının düzenli ve doğrulanmış okunması sağlandı.', 'uploads/site/projeler/svc-su.jpg', 4
    UNION ALL SELECT 'dogalgaz-endeks-projesi', 'Doğalgaz Endeks Projesi', 'Sayaç Okuma', 'Hatay', 'Doğalgaz endeks okuma ve abone verisi doğrulama çalışması.', 'Doğalgaz endeks okuma ve abone verisi doğrulama çalışması yürütüldü.', 'uploads/site/projeler/svc-gaz.jpg', 5
    UNION ALL SELECT 'dijital-saha-takibi', 'Dijital Saha Takibi', 'Teknoloji', 'Hatay', 'Saha operasyonlarının mobil uygulama ve panel ile anlık izlenmesi.', 'Saha operasyonlarının mobil uygulama ve yönetim paneli ile anlık izlenmesini sağlayan dijital takip altyapısı kuruldu.', 'uploads/site/projeler/svc-teknik.jpg', 6
) d
WHERE NOT EXISTS (SELECT 1 FROM site_projeler s WHERE s.slug = d.slug);

-- ===== 2026_11_07_site_db_referanslar_ornek_veri.sql =====
INSERT INTO site_referanslar (kurum, kisi, unvan, yorum, sira, durum)
SELECT d.* FROM (
    SELECT 'Örnek Müşteri A.Ş.' AS kurum, NULL AS kisi, 'Operasyon Müdürü' AS unvan, 'Saha ekiplerinin işleyişi ve raporlama kalitesi beklentimizin üzerinde. Süreçler şeffaf ve zamanında ilerliyor.' AS yorum, 1 AS sira, 'yayinda' AS durum
    UNION ALL SELECT 'Örnek Enerji Ltd.', NULL, 'Proje Yöneticisi', 'Mobil takip sayesinde her işin durumunu anlık görüyoruz. Hata oranımız belirgin biçimde azaldı.', 2, 'yayinda'
    UNION ALL SELECT 'Örnek Dağıtım A.Ş.', NULL, 'Teknik Direktör', 'Uzun süredir çalıştığımız güvenilir bir çözüm ortağı. Ekipler disiplinli ve iletişim çok hızlı.', 3, 'yayinda'
) d
WHERE NOT EXISTS (SELECT 1 FROM site_referanslar);

-- ===== 2026_11_07_site_db_sss_ornek_veri.sql =====
INSERT INTO site_sss (soru, cevap, sira, durum)
SELECT d.* FROM (
    SELECT 'Hangi bölgelerde hizmet veriyorsunuz?' AS soru, 'Hatay merkezli olmak üzere, proje ihtiyacına göre farklı illerde ekip konuşlandırıyoruz.' AS cevap, 1 AS sira, 'yayinda' AS durum
    UNION ALL SELECT 'Saha personeli nasıl eğitiliyor?', 'Tüm personelimiz işe başlamadan önce teknik ve iş güvenliği eğitiminden geçer, düzenli olarak yenilenir.', 2, 'yayinda'
    UNION ALL SELECT 'Okuma verileri nasıl iletiliyor?', 'Veriler mobil uygulama ile fotoğraf ve konum bilgisiyle kaydedilir, sistemlerinize anlık aktarılır.', 3, 'yayinda'
    UNION ALL SELECT 'Teklif almak için ne yapmalıyım?', 'İletişim sayfasındaki formu doldurabilir veya bizi doğrudan arayabilirsiniz.', 4, 'yayinda'
) d
WHERE NOT EXISTS (SELECT 1 FROM site_sss);

-- ===== 2026_11_07_site_db_istatistikler_ornek_veri.sql =====
INSERT INTO site_istatistikler (etiket, deger, sonek, sira, durum)
SELECT d.* FROM (
    SELECT 'Tamamlanan Proje' AS etiket, 1200 AS deger, '+' AS sonek, 1 AS sira, 'yayinda' AS durum
    UNION ALL SELECT 'Saha Personeli', 350, '+', 2, 'yayinda'
    UNION ALL SELECT 'Müşteri Memnuniyeti', 98, '%', 3, 'yayinda'
    UNION ALL SELECT 'Yıllık Tecrübe', 15, '+', 4, 'yayinda'
) d
WHERE NOT EXISTS (SELECT 1 FROM site_istatistikler);

