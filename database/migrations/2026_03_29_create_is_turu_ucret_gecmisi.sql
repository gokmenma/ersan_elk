-- İş türü ücretlerini tarih bazlı versiyonlamak için yeni tablo
CREATE TABLE IF NOT EXISTS `is_turu_ucret_gecmisi` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `firma_id` int(11) NOT NULL,
  `is_turu_id` int(11) NOT NULL,
  `ucret` decimal(10,2) NOT NULL DEFAULT 0.00,
  `aracli_ucret` decimal(10,2) NOT NULL DEFAULT 0.00,
  `gecerlilik_baslangic` date NOT NULL,
  `gecerlilik_bitis` date DEFAULT NULL,
  `olusturma_tarihi` datetime NOT NULL DEFAULT current_timestamp(),
  `guncelleme_tarihi` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `silinme_tarihi` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_is_turu_ucret_tarih` (`firma_id`, `is_turu_id`, `gecerlilik_baslangic`, `gecerlilik_bitis`, `silinme_tarihi`),
  KEY `idx_is_turu_ucret_lookup` (`is_turu_id`, `firma_id`, `silinme_tarihi`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Mevcut tanımlı ücretleri başlangıç verisi olarak taşı
-- Not: geçmiş başlangıç tarihi bilinmediği için genel başlangıç olarak 2000-01-01 atanır.
INSERT INTO `is_turu_ucret_gecmisi` (
  `firma_id`,
  `is_turu_id`,
  `ucret`,
  `aracli_ucret`,
  `gecerlilik_baslangic`,
  `gecerlilik_bitis`,
  `olusturma_tarihi`
)
SELECT
  t.firma_id,
  t.id as is_turu_id,
  COALESCE(t.is_turu_ucret, 0) as ucret,
  COALESCE(t.aracli_personel_is_turu_ucret, 0) as aracli_ucret,
  '2000-01-01' as gecerlilik_baslangic,
  NULL as gecerlilik_bitis,
  NOW() as olusturma_tarihi
FROM tanimlamalar t
WHERE t.grup = 'is_turu'
  AND t.silinme_tarihi IS NULL
  AND NOT EXISTS (
    SELECT 1
    FROM is_turu_ucret_gecmisi g
    WHERE g.is_turu_id = t.id
      AND g.firma_id = t.firma_id
      AND g.silinme_tarihi IS NULL
  );
