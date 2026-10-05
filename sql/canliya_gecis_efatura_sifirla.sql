-- ============================================================================
-- Canlıya Geçiş Öncesi E-Fatura / Fatura Test Verilerini Sıfırlama Scripti
-- Tarih: 2026-10-05
-- Açıklama:
--   Canlı ortama geçiş öncesinde geliştirme/test sürecinde oluşturulan tüm
--   test faturaları, satırları, tahsilatları, logları ve senkronizasyon kuyruklarını temizler.
--   NOT: Firma ayarları (efatura_ayarlar) ve Not Şablonları (efatura_not_sablonlari) korunur.
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- 1. Fatura Ana ve Detay Tabloları
TRUNCATE TABLE `fatura_satirlari`;
TRUNCATE TABLE `fatura_tahsilatlari`;
TRUNCATE TABLE `faturalar`;

-- 2. Log, Kuyruk ve Senkronizasyon Geçmişi
TRUNCATE TABLE `efatura_islem_gecmisi`;
TRUNCATE TABLE `efatura_loglari`;
TRUNCATE TABLE `efatura_senkronizasyon`;
TRUNCATE TABLE `efatura_sync_jobs`;

-- 3. Fatura Seri/Numaratör Sayaçları (Test serilerini temizlemek isterseniz)
TRUNCATE TABLE `efatura_numarator`;

SET FOREIGN_KEY_CHECKS = 1;
