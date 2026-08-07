-- Migration: Fix yonetilen_departman delimiter and department name handling
-- Date: 2026-08-07

-- Description:
-- Update yonetilen_departman handling to use '|' as delimiter instead of ','
-- so department names containing commas (such as 'Kaçak Kontrol,Mühürleme') are correctly preserved.

-- No schema changes required as yonetilen_departman is TEXT/VARCHAR.
