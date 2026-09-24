-- Credential Manager - Fresh Client Database Schema
-- IMPORTANT:
-- This schema contains structure only.
-- No production/client data is included.
-- The first administrator is created by public/setup.php.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS credential_activity_logs;
DROP TABLE IF EXISTS credential_access_logs;
DROP TABLE IF EXISTS credentials;
DROP TABLE IF EXISTS activity_logs;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS employees;
DROP TABLE IF EXISTS departments;
DROP TABLE IF EXISTS app_settings;

SET FOREIGN_KEY_CHECKS = 1;


-- =========================================================
-- APPLICATION SETTINGS
-- =========================================================

CREATE TABLE app_settings (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    setting_key VARCHAR(100) NOT NULL,
    setting_value TEXT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY setting_key (setting_key)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


-- =========================================================
-- DEPARTMENTS
-- =========================================================

CREATE TABLE departments (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    department_name VARCHAR(100) NOT NULL,
    description TEXT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY department_name (department_name)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


-- =========================================================
-- EMPLOYEES
-- =========================================================

CREATE TABLE employees (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    employee_name VARCHAR(150) NOT NULL,
    employee_id VARCHAR(50) NOT NULL,
    department_id INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY unique_employee_id (employee_id),
    KEY department_id (department_id),

    CONSTRAINT employees_ibfk_1
        FOREIGN KEY (department_id)
        REFERENCES departments (id)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


-- =========================================================
-- USERS
-- =========================================================

CREATE TABLE users (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    employee_id VARCHAR(50) NULL,
    full_name VARCHAR(100) NOT NULL,
    username VARCHAR(50) NOT NULL,
    email VARCHAR(150) NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin','editor','viewer') NOT NULL DEFAULT 'viewer',
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    two_factor_enabled TINYINT(1) NOT NULL DEFAULT 0,
    two_factor_secret VARCHAR(255) NULL,
    two_factor_confirmed_at DATETIME NULL,

    PRIMARY KEY (id),
    UNIQUE KEY unique_employee_id (employee_id),
    UNIQUE KEY username (username),
    UNIQUE KEY email (email)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


-- =========================================================
-- ACTIVITY LOGS
-- =========================================================

CREATE TABLE activity_logs (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NULL,
    action VARCHAR(100) NOT NULL,
    description TEXT NOT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    KEY user_id (user_id),

    CONSTRAINT activity_logs_ibfk_1
        FOREIGN KEY (user_id)
        REFERENCES users (id)
        ON UPDATE RESTRICT
        ON DELETE SET NULL
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


-- =========================================================
-- CREDENTIALS
-- =========================================================

CREATE TABLE credentials (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    service_name VARCHAR(150) NOT NULL,
    service_url VARCHAR(255) NULL,
    credential_username VARCHAR(150) NULL,
    credential_password TEXT NULL,
    notes TEXT NULL,
    change_comment TEXT NULL,
    is_new_version TINYINT(1) NOT NULL DEFAULT 0,
    created_by INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    deleted_by INT UNSIGNED NULL,
    employee_id INT UNSIGNED NULL,
    parent_credential_id INT UNSIGNED NULL,

    PRIMARY KEY (id),

    KEY created_by (created_by),
    KEY fk_credentials_deleted_by (deleted_by),
    KEY fk_credentials_employee (employee_id),
    KEY idx_deleted_at (deleted_at),
    KEY idx_parent_credential_id (parent_credential_id),

    CONSTRAINT credentials_ibfk_1
        FOREIGN KEY (created_by)
        REFERENCES users (id)
        ON UPDATE RESTRICT
        ON DELETE CASCADE,

    CONSTRAINT fk_credentials_deleted_by
        FOREIGN KEY (deleted_by)
        REFERENCES users (id)
        ON UPDATE RESTRICT
        ON DELETE SET NULL,

    CONSTRAINT fk_credentials_employee
        FOREIGN KEY (employee_id)
        REFERENCES employees (id)
        ON UPDATE RESTRICT
        ON DELETE CASCADE,

    CONSTRAINT fk_credentials_parent
        FOREIGN KEY (parent_credential_id)
        REFERENCES credentials (id)
        ON UPDATE RESTRICT
        ON DELETE SET NULL
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


-- =========================================================
-- CREDENTIAL ACCESS LOGS
-- =========================================================

CREATE TABLE credential_access_logs (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    credential_id INT UNSIGNED NOT NULL,
    action ENUM('show','copy') NOT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),

    KEY idx_created_at (created_at),
    KEY idx_credential_id (credential_id),
    KEY idx_user_id (user_id),

    CONSTRAINT fk_log_credential
        FOREIGN KEY (credential_id)
        REFERENCES credentials (id)
        ON UPDATE RESTRICT
        ON DELETE CASCADE,

    CONSTRAINT fk_log_user
        FOREIGN KEY (user_id)
        REFERENCES users (id)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


-- =========================================================
-- CREDENTIAL ACTIVITY LOGS
-- =========================================================
-- Production metadata showed no foreign-key constraints
-- on this table, so none are added here.

CREATE TABLE credential_activity_logs (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    credential_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    action ENUM('show','copy') NOT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    KEY credential_id (credential_id),
    KEY user_id (user_id)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


-- =========================================================
-- INITIAL APPLICATION STATE
-- =========================================================
-- setup.php changes this to 1 after creating the first admin.

INSERT INTO app_settings (
    setting_key,
    setting_value
) VALUES (
    'installation_completed',
    '0'
);


SET FOREIGN_KEY_CHECKS = 1;
