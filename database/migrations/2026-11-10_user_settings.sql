-- DiSSENT migration: per-user settings (Settings page).
-- Date: 10/11/2026
--
-- Adds the user_settings table for the language and notification
-- preferences on Pages/settings.php. One row per user; a user without a
-- row simply uses the defaults (English, all notifications on).
--
-- Safe to run more than once (CREATE TABLE IF NOT EXISTS).
-- Run it against the website database, for example:
--   docker exec -i dissent-mysql mysql -udissent -pdissent_dev dissent_local < database/migrations/2026-11-10_user_settings.sql
--
-- This folder is outside docs/, so these files are never served by the website.

CREATE TABLE IF NOT EXISTS user_settings (
    user_id INT NOT NULL,
    language VARCHAR(5) NOT NULL DEFAULT 'en',
    notify_report_updates TINYINT(1) NOT NULL DEFAULT 1,
    notify_messages TINYINT(1) NOT NULL DEFAULT 1,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id),
    CONSTRAINT fk_user_settings_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
