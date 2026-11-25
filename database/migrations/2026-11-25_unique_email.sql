-- DiSSENT migration: one account per email address. 25/11/2026
--
-- Adds a unique key on users.email only if there is none yet.
-- If two accounts share an email, it stops with an error and changes nothing.
-- Safe to run more than once.
--   Get-Content .\database\migrations\2026-11-25_unique_email.sql | docker exec -i dissent-mysql mysql -udissent -pdissent_dev dissent_local

SET @has_key = (
    SELECT COUNT(*) FROM (
        SELECT INDEX_NAME FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND NON_UNIQUE = 0
        GROUP BY INDEX_NAME
        HAVING COUNT(*) = 1 AND MAX(COLUMN_NAME) = 'email'
    ) AS unique_email_keys
);
SET @ddl = IF(@has_key > 0,
    "SELECT 'users.email is already unique, nothing to do' AS result",
    'ALTER TABLE users ADD UNIQUE KEY uq_users_email (email)');
PREPARE s FROM @ddl;
EXECUTE s;
DEALLOCATE PREPARE s;
