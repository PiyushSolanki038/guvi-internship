-- =============================================================================
-- db_setup.sql
-- Run this script once in MySQL to create the database and users table.
--
-- Usage:
--   mysql -u root -p < php/db_setup.sql
--
-- MySQL stores: user registration data (email, username, hashed password)
-- MongoDB stores: user profile details (age, dob, contact, etc.)
-- =============================================================================

-- Create the database
CREATE DATABASE IF NOT EXISTS guvi_auth
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE guvi_auth;

-- ── Users table ──────────────────────────────────────────────────────────────
-- Stores credentials for authentication only.
-- Profile details (age, dob, contact) are stored in MongoDB.
CREATE TABLE IF NOT EXISTS users (
  id            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  username      VARCHAR(80)     NOT NULL,
  email         VARCHAR(180)    NOT NULL,
  password_hash VARCHAR(255)    NOT NULL,    -- bcrypt hash (never plain-text)
  created_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  UNIQUE KEY uq_email (email)               -- enforce unique email at DB level
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
