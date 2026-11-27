-- DiSSENT: AI compliance check results (26/11/2026). See ai/README.md.
-- One row per held message when AI_ENABLED=1: the AI's decision, reasons, cited passages,
-- ReAct steps and model, shown to the admin as "AI suggestion - the admin decides".
-- The admin's own decision and whether it matches the AI are added when they decide
-- (for the evaluation). Safe to run again.
--
-- Run (PowerShell, from DiSSENT-clean), after backing up the database:
--   Get-Content .\database\migrations\2026-11-26_ai_checks.sql | docker exec -i dissent-mysql mysql -udissent -pdissent_dev dissent_local

CREATE TABLE IF NOT EXISTS message_ai_checks (
    id INT NOT NULL AUTO_INCREMENT,
    message_id INT NOT NULL,
    status ENUM('ok', 'failed') NOT NULL,          -- failed: the AI service did not answer correctly (message held)
    decision ENUM('ALLOW', 'REVIEW') NOT NULL,
    risk_level VARCHAR(20) NOT NULL,
    reasons JSON NOT NULL,
    citations JSON NOT NULL,                       -- [{source, country, passage_id, quote}]
    steps JSON NOT NULL,                           -- ReAct trace [{thought, action, observation}]
    model_name VARCHAR(60) NOT NULL,
    latency_ms INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    admin_decision ENUM('approved', 'rejected') NULL DEFAULT NULL,
    -- 1 = the admin agreed with the AI (AI said REVIEW and the admin rejected, or AI said ALLOW
    -- and the admin approved); 0 = the admin disagreed; NULL = no decision yet, or the AI failed.
    admin_agreed TINYINT(1) NULL DEFAULT NULL,
    admin_decided_at TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_ai_check_message (message_id),
    KEY idx_ai_check_agreed (admin_agreed)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
