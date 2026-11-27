-- DiSSENT: consent on Register (26/11/2026).
-- Adds users.consent_at: when the person ticked "I agree to the Terms of use and
-- have read the Privacy notice". NULL for accounts made before this change.
-- Safe to run again.
--
-- Run (PowerShell, from DiSSENT-clean):
--   Get-Content .\database\migrations\2026-11-26_consent.sql | docker exec -i dissent-mysql mysql -udissent -pdissent_dev dissent_local

SET @has_col = (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'consent_at');
SET @sql = IF(@has_col = 0,
    'ALTER TABLE users ADD COLUMN consent_at TIMESTAMP NULL DEFAULT NULL',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
