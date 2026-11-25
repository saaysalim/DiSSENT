-- DiSSENT migration: message review (compliance check) and attachments. 25/11/2026
--
-- Adds columns to `messages`, and two new tables. Nothing is removed.
-- Safe to run more than once. Back up the database first (see README).
--   Get-Content .\database\migrations\2026-11-25_message_review.sql | docker exec -i dissent-mysql mysql -udissent -pdissent_dev dissent_local

DROP PROCEDURE IF EXISTS dissent_add_column;
DELIMITER //
CREATE PROCEDURE dissent_add_column(IN col VARCHAR(64), IN definition VARCHAR(255))
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'messages' AND COLUMN_NAME = col
    ) THEN
        SET @ddl = CONCAT('ALTER TABLE messages ADD COLUMN ', col, ' ', definition);
        PREPARE s FROM @ddl;
        EXECUTE s;
        DEALLOCATE PREPARE s;
    END IF;
END //
DELIMITER ;

-- status: delivered, pending_review, rejected, withdrawn.
-- Messages that already exist become "delivered".
CALL dissent_add_column('status', "VARCHAR(20) NOT NULL DEFAULT 'delivered'");
CALL dissent_add_column('review_reasons', 'TEXT NULL');
CALL dissent_add_column('review_note', 'VARCHAR(500) NULL');
CALL dissent_add_column('delivered_at', 'TIMESTAMP NULL');
CALL dissent_add_column('changed_at', 'TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3)');
DROP PROCEDURE dissent_add_column;

-- From now on, anything saved without a status is held back, not delivered.
ALTER TABLE messages ALTER COLUMN status SET DEFAULT 'pending_review';

-- One attached file per message. The file itself is in private/message-files/.
CREATE TABLE IF NOT EXISTS message_files (
    id INT NOT NULL AUTO_INCREMENT,
    message_id INT NOT NULL,
    stored_name VARCHAR(60) NOT NULL,
    original_name VARCHAR(160) NOT NULL,
    mime VARCHAR(40) NOT NULL,
    file_size INT UNSIGNED NOT NULL,
    sha256 CHAR(64) NOT NULL,
    removed_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_message_files_message (message_id),
    CONSTRAINT fk_message_files_message FOREIGN KEY (message_id) REFERENCES messages (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Audit log: who decided what, when and why. Kept even if a message is removed.
-- decision: held, approved, rejected, withdrawn. reviewer_id is NULL for automatic steps.
CREATE TABLE IF NOT EXISTS message_reviews (
    id INT NOT NULL AUTO_INCREMENT,
    message_id INT NOT NULL,
    reviewer_id INT NULL,
    decision VARCHAR(20) NOT NULL,
    reasons TEXT NULL,
    note VARCHAR(500) NULL,
    checker VARCHAR(40) NULL,
    file_name VARCHAR(160) NULL,
    file_sha256 CHAR(64) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY ix_message_reviews_message (message_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
