-- ============================================================================
-- Faturalar Tablosu Boyut Optimizasyonu ve Disk Alanı Geri Kazanımı
-- Tarih: 2026-10-05
-- Açıklama:
--   Tüm fatura UBL XML dosyaları diskte (storage/invoices/...) güvenli şekilde
--   saklandığı ve ubl_xml_path üzerinden erişildiği için, veritabanındaki
--   mükerrer tutulan mediumtext kaynak_xml verisi temizlenir ve tablo optimize edilir.
-- ============================================================================

-- 1. Diskte dosyası bulunan ve kaynak_xml dolu olan kayıtların veritabanındaki XML metnini boşalt
UPDATE faturalar 
SET kaynak_xml = NULL 
WHERE ubl_xml_path IS NOT NULL 
  AND ubl_xml_path != '' 
  AND kaynak_xml IS NOT NULL;

-- 2. Tabloyu optimize ederek işletim sistemi / MySQL disk alanını geri kazan
OPTIMIZE TABLE faturalar;
