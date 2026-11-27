-- DiSSENT: unread message counts (26/11/2026).
-- Adds messages.read_at: when the receiver opened the conversation. NULL = unread.
-- Messages that already exist count as read, so nobody gets a pile of old "unread" badges.
-- Safe to run again.
--
-- Run (PowerShell, from DiSSENT-clean):
--   Get-Content .\database\migrations\2026-11-26_message_read.sql | docker exec -i dissent-mysql mysql -udissent -pdissent_dev dissent_local

SET @has_col = (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'messages' AND COLUMN_NAME = 'read_at');
SET @sql = IF(@has_col = 0,
    'ALTER TABLE messages ADD COLUMN read_at TIMESTAMP NULL DEFAULT NULL',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Old messages count as read (only on the first run, when the column was just added).
-- changed_at is kept so open chats are not redrawn.
SET @sql = IF(@has_col = 0,
    'UPDATE messages SET read_at = COALESCE(delivered_at, sent_at), changed_at = changed_at WHERE read_at IS NULL',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Fast unread counts per receiver.
SET @has_ix = (SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'messages' AND INDEX_NAME = 'ix_messages_unread');
SET @sql = IF(@has_ix = 0,
    'CREATE INDEX ix_messages_unread ON messages (receiver_id, status, read_at)',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
