-- DiSSENT migration: workshop security fixes. 25/11/2026
--
-- 1. users.session_version: sessions end when the password changes or the account is deleted.
-- 2. auth_attempts: limits failed logins and password-reset requests (email stored as a hash).
-- 3. Password-reset tokens are now stored as SHA-256 hashes, so every old reset link stops working.
-- Safe to run more than once.
--   Get-Content .\database\migrations\2026-11-25_security.sql | docker exec -i dissent-mysql mysql -udissent -pdissent_dev dissent_local

SET @has_col = (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'session_version');
SET @ddl = IF(@has_col > 0, 'SELECT 1', 'ALTER TABLE users ADD COLUMN session_version INT UNSIGNED NOT NULL DEFAULT 1');
PREPARE s FROM @ddl;
EXECUTE s;
DEALLOCATE PREPARE s;

CREATE TABLE IF NOT EXISTS auth_attempts (
    id INT NOT NULL AUTO_INCREMENT,
    kind VARCHAR(20) NOT NULL,
    email_hash CHAR(64) NOT NULL,
    ip VARCHAR(45) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY ix_auth_attempts_lookup (kind, email_hash, ip, created_at),
    KEY ix_auth_attempts_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Old rows hold the raw token. New rows hold sha256(token) and have token_hashed = 1.
-- Old links cannot be used after this change, so they are removed (only on the first run).
SET @has_col = (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'password_resets' AND COLUMN_NAME = 'token_hashed');
SET @ddl = IF(@has_col > 0, 'SELECT 1', 'ALTER TABLE password_resets ADD COLUMN token_hashed TINYINT(1) NOT NULL DEFAULT 0');
PREPARE s FROM @ddl;
EXECUTE s;
DEALLOCATE PREPARE s;
DELETE FROM password_resets WHERE token_hashed = 0;
