-- Credential Manager: TOTP 2FA migration
-- Safe to run more than once on MariaDB versions supporting IF NOT EXISTS.
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS two_factor_enabled TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS two_factor_secret VARCHAR(64) NULL,
    ADD COLUMN IF NOT EXISTS two_factor_confirmed_at DATETIME NULL;
