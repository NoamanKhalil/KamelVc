-- Kamel Ventures — Signup Table
-- Run once in Hostinger phpMyAdmin → SQL tab
-- If the table already exists, run only the ALTER TABLE line at the bottom.

CREATE TABLE IF NOT EXISTS signups (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    first_name  VARCHAR(100) NOT NULL,
    last_name   VARCHAR(100) NOT NULL,
    email       VARCHAR(255) NOT NULL,
    role        ENUM('investor','founder','mentor','other','') DEFAULT '',
    amount      INT UNSIGNED DEFAULT NULL,
    ip_address  VARCHAR(45)  DEFAULT NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_email (email),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migration: add amount column to an existing table
-- ALTER TABLE signups ADD COLUMN amount INT UNSIGNED DEFAULT NULL AFTER role;
