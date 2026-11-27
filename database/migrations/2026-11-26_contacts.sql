-- DiSSENT: private contacts and message requests (26/11/2026).
-- Members no longer see a list of everyone. Two people can message each other only
-- after one sent a message request (by exact email address) and the other accepted.
-- Safe to run again.
--
-- Run (PowerShell, from DiSSENT-clean), after backing up the database:
--   Get-Content .\database\migrations\2026-11-26_contacts.sql | docker exec -i dissent-mysql mysql -udissent -pdissent_dev dissent_local

-- One row per pair of people (in either direction).
--   status:  pending   waiting for the addressee
--            accepted  both can message each other
--            declined  the addressee said no (the requester is not told)
--            blocked   the addressee blocked the requester (no more requests)
--   source:  request       made with "New message"
--            conversation  made by this migration (the pair already had a delivered conversation)
--            Later, for example 'centre' when members of the same media centre are connected
--            automatically (media centre roles plan); centre_id is kept for that.
-- No foreign keys: MySQL does not allow them on columns used by the generated pair columns.
-- Accounts are never removed from users (they become role 'deleted'), and accepted rows are
-- kept so the other person can still read the conversation.
CREATE TABLE IF NOT EXISTS contacts (
    id INT NOT NULL AUTO_INCREMENT,
    requester_id INT NOT NULL,
    addressee_id INT NOT NULL,
    status ENUM('pending', 'accepted', 'declined', 'blocked') NOT NULL DEFAULT 'pending',
    source VARCHAR(20) NOT NULL DEFAULT 'request',
    centre_id INT NULL DEFAULT NULL,
    first_message_id INT NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    decided_at TIMESTAMP NULL DEFAULT NULL,
    user_low INT AS (LEAST(requester_id, addressee_id)) STORED,
    user_high INT AS (GREATEST(requester_id, addressee_id)) STORED,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_contact_pair (user_low, user_high),
    KEY idx_contacts_addressee (addressee_id, status),
    KEY idx_contacts_requester (requester_id, status),
    CONSTRAINT chk_contacts_not_self CHECK (requester_id <> addressee_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Every "New message" attempt, for the limits (20 a day per person, 5 a week to the same
-- address). Only a SHA-256 hash of the typed address is kept, also for addresses that have
-- no account. Rows older than 8 days are deleted automatically.
CREATE TABLE IF NOT EXISTS contact_request_log (
    id INT NOT NULL AUTO_INCREMENT,
    requester_id INT NOT NULL,
    email_hash CHAR(64) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_request_log_requester (requester_id, created_at),
    KEY idx_request_log_pair (requester_id, email_hash, created_at),
    CONSTRAINT fk_request_log_requester FOREIGN KEY (requester_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Existing conversations stay: every pair with at least one delivered message is accepted.
INSERT IGNORE INTO contacts (requester_id, addressee_id, status, source, created_at, decided_at)
SELECT LEAST(m.sender_id, m.receiver_id), GREATEST(m.sender_id, m.receiver_id),
       'accepted', 'conversation', MIN(m.sent_at), CURRENT_TIMESTAMP
FROM messages m
JOIN users a ON a.id = m.sender_id
JOIN users b ON b.id = m.receiver_id
WHERE m.status = 'delivered' AND m.sender_id <> m.receiver_id
GROUP BY LEAST(m.sender_id, m.receiver_id), GREATEST(m.sender_id, m.receiver_id);
