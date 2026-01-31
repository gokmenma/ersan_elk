-- Migration: Add islem_id column to endeks_okuma table
-- Date: 2026-01-31
-- Description: Online sorgulama için islem_id kolonu ekleniyor

-- Check if column exists and add if not
ALTER TABLE `endeks_okuma` 
ADD COLUMN IF NOT EXISTS `islem_id` VARCHAR(100) NULL DEFAULT NULL AFTER `id`;

-- Add index for faster lookups
CREATE INDEX IF NOT EXISTS `idx_endeks_okuma_islem_id` ON `endeks_okuma` (`islem_id`);
