-- Run against the existing DiSSENT database (InfinityFree or local) before the
-- secure-messaging bridge will work. Safe to run once; re-running will error
-- with "duplicate column" rather than silently duplicating it.

ALTER TABLE messages ADD COLUMN envelope_id VARCHAR(64) NULL;
