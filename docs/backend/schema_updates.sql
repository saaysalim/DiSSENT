-- DiSSENT secure-messaging schema update.
-- Select the application's database before running this script.
--
-- Check whether the column already exists:
-- SHOW COLUMNS FROM messages LIKE 'envelope_id';
--
-- Run the statement below ONLY if the column is missing.
-- Running it again will produce a duplicate-column error.

ALTER TABLE messages
    ADD COLUMN envelope_id VARCHAR(64) NULL;