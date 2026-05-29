-- ============================================================================
-- ELHOE Verification System - Schema
-- Run once on a fresh MySQL database (Hostinger phpMyAdmin -> Import).
-- Charset: utf8mb4 throughout (full Unicode + emoji safe).
-- ============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- -------------------------------------------------------------------------
-- products
-- -------------------------------------------------------------------------
DROP TABLE IF EXISTS `products`;
CREATE TABLE `products` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `wp_product_id` INT NULL COMMENT 'Original WordPress post ID (preserves legacy mapping)',
  `sku` VARCHAR(64) NULL,
  `name` VARCHAR(255) NOT NULL,
  `slug` VARCHAR(255) NULL,
  `category` VARCHAR(100) NULL,
  `routine_group` VARCHAR(100) NULL COMMENT 'Used for "Complete Your Routine" cluster',
  `image_url` VARCHAR(500) NULL,
  `product_url` VARCHAR(500) NULL COMMENT 'Link to WP product page (Buy Again button)',
  `description` TEXT NULL,
  `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uk_sku` (`sku`),
  UNIQUE KEY `uk_slug` (`slug`),
  KEY `idx_wp_product_id` (`wp_product_id`),
  KEY `idx_status` (`status`),
  KEY `idx_routine_group` (`routine_group`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------------
-- codes  (the core verification table)
-- code stored as VARCHAR to preserve leading zeros (e.g. 0457667893450)
-- -------------------------------------------------------------------------
DROP TABLE IF EXISTS `codes`;
CREATE TABLE `codes` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `code` VARCHAR(32) NOT NULL COMMENT 'As entered/imported',
  `code_normalized` VARCHAR(32) NOT NULL COMMENT 'Digits-only, used for lookup',
  `product_id` INT NOT NULL,
  `batch_number` VARCHAR(64) NULL,
  `expiry_date` DATE NULL,
  `mode` ENUM('unique','universal') NOT NULL DEFAULT 'universal',
  `status` ENUM('active','inactive','quarantined') NOT NULL DEFAULT 'active',
  `scan_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `first_scanned_at` TIMESTAMP NULL,
  `first_scan_ip` VARCHAR(45) NULL,
  `last_scanned_at` TIMESTAMP NULL,
  `risk_score` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0-100 counterfeit risk',
  `owner_customer_id` INT NULL COMMENT 'Set on first registration (unique mode)',
  `registered_at` TIMESTAMP NULL,
  `recommended_product_ids` VARCHAR(255) NULL COMMENT 'Comma-separated, override default routine_group',
  `notes` TEXT NULL COMMENT 'Internal admin notes',
  `created_by` INT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uk_code_normalized` (`code_normalized`),
  KEY `idx_product` (`product_id`),
  KEY `idx_batch` (`batch_number`),
  KEY `idx_status` (`status`),
  KEY `idx_mode` (`mode`),
  KEY `idx_risk` (`risk_score`),
  KEY `idx_owner` (`owner_customer_id`),
  CONSTRAINT `fk_codes_product` FOREIGN KEY (`product_id`)
    REFERENCES `products`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------------
-- scan_logs  (every verification attempt)
-- -------------------------------------------------------------------------
DROP TABLE IF EXISTS `scan_logs`;
CREATE TABLE `scan_logs` (
  `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
  `code_input` VARCHAR(255) NULL COMMENT 'Raw user input (for forensic analysis)',
  `code_normalized` VARCHAR(32) NULL,
  `code_id` INT NULL,
  `product_id` INT NULL,
  `result` ENUM(
    'valid_unique_first',
    'valid_unique_returning',
    'valid_universal',
    'already_registered',
    'quarantined',
    'invalid',
    'rate_limited',
    'malformed'
  ) NOT NULL,
  `ip_address` VARCHAR(45) NULL,
  `ip_country` VARCHAR(2) NULL,
  `ip_country_name` VARCHAR(100) NULL,
  `ip_city` VARCHAR(100) NULL,
  `user_agent` VARCHAR(500) NULL,
  `referrer` VARCHAR(500) NULL,
  `session_id` VARCHAR(64) NULL,
  `scanned_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_code_norm` (`code_normalized`),
  KEY `idx_time` (`scanned_at`),
  KEY `idx_result` (`result`),
  KEY `idx_ip` (`ip_address`),
  KEY `idx_product` (`product_id`),
  KEY `idx_code_id` (`code_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------------
-- customers  (VIP list)
-- -------------------------------------------------------------------------
DROP TABLE IF EXISTS `customers`;
CREATE TABLE `customers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `email` VARCHAR(255) NULL,
  `email_hash` CHAR(64) NULL COMMENT 'SHA256 lowercased email - for Meta CAPI',
  `phone` VARCHAR(32) NULL,
  `phone_hash` CHAR(64) NULL COMMENT 'SHA256 normalized phone - for Meta CAPI',
  `first_name` VARCHAR(100) NULL,
  `last_name` VARCHAR(100) NULL,
  `city` VARCHAR(100) NULL,
  `country` VARCHAR(2) NULL,
  `consent_marketing` TINYINT(1) NOT NULL DEFAULT 0,
  `first_product_id` INT NULL,
  `first_code_id` INT NULL,
  `registered_ip` VARCHAR(45) NULL,
  `registered_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_email` (`email`),
  KEY `idx_phone` (`phone`),
  KEY `idx_email_hash` (`email_hash`),
  KEY `idx_registered_at` (`registered_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------------
-- admin_users
-- -------------------------------------------------------------------------
DROP TABLE IF EXISTS `admin_users`;
CREATE TABLE `admin_users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `role` ENUM('super_admin','manager','marketer') NOT NULL DEFAULT 'manager',
  `last_login_at` TIMESTAMP NULL,
  `last_login_ip` VARCHAR(45) NULL,
  `failed_attempts` INT UNSIGNED NOT NULL DEFAULT 0,
  `locked_until` TIMESTAMP NULL,
  `status` ENUM('active','disabled') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uk_username` (`username`),
  UNIQUE KEY `uk_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------------
-- audit_log  (admin action history)
-- -------------------------------------------------------------------------
DROP TABLE IF EXISTS `audit_log`;
CREATE TABLE `audit_log` (
  `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
  `admin_id` INT NULL,
  `admin_username` VARCHAR(50) NULL COMMENT 'Cached for display if admin deleted',
  `action` VARCHAR(100) NOT NULL,
  `entity_type` VARCHAR(50) NULL,
  `entity_id` BIGINT NULL,
  `details` JSON NULL,
  `ip_address` VARCHAR(45) NULL,
  `user_agent` VARCHAR(500) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_admin` (`admin_id`),
  KEY `idx_time` (`created_at`),
  KEY `idx_action` (`action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------------
-- settings  (key-value runtime config)
-- -------------------------------------------------------------------------
DROP TABLE IF EXISTS `settings`;
CREATE TABLE `settings` (
  `key_name` VARCHAR(100) PRIMARY KEY,
  `value` TEXT NULL,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------------
-- rate_limits  (per IP, per bucket)
-- -------------------------------------------------------------------------
DROP TABLE IF EXISTS `rate_limits`;
CREATE TABLE `rate_limits` (
  `ip_address` VARCHAR(45) NOT NULL,
  `bucket` VARCHAR(50) NOT NULL,
  `count` INT UNSIGNED NOT NULL DEFAULT 1,
  `window_start` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`ip_address`, `bucket`),
  KEY `idx_window` (`window_start`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------------
-- ip_geo_cache  (cache GeoIP lookups for 30 days to spare API calls)
-- -------------------------------------------------------------------------
DROP TABLE IF EXISTS `ip_geo_cache`;
CREATE TABLE `ip_geo_cache` (
  `ip_address` VARCHAR(45) PRIMARY KEY,
  `country_code` VARCHAR(2) NULL,
  `country_name` VARCHAR(100) NULL,
  `city` VARCHAR(100) NULL,
  `region` VARCHAR(100) NULL,
  `latitude` DECIMAL(10, 6) NULL,
  `longitude` DECIMAL(10, 6) NULL,
  `provider` VARCHAR(20) NULL,
  `cached_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_country` (`country_code`),
  KEY `idx_cached_at` (`cached_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------------
-- Default settings rows
-- -------------------------------------------------------------------------
INSERT INTO `settings` (`key_name`, `value`) VALUES
  ('brand_name',          'ELHOE'),
  ('brand_tagline',       'Redefine Your Skincare Journey'),
  ('brand_logo_url',      ''),
  ('brand_primary_color', '#3E5641'),
  ('brand_accent_color',  '#A4B494'),
  ('brand_bg_color',      '#F5F1E8'),
  ('support_email',       'support@elhoe.com'),
  ('support_whatsapp',    ''),
  ('meta_pixel_id',       ''),
  ('meta_capi_token',     ''),
  ('meta_capi_test_code', ''),
  ('gtm_container_id',    ''),
  ('admin_alert_email',   ''),
  ('alert_quarantine_threshold', '70'),
  ('alert_failed_scans_per_ip', '20')
ON DUPLICATE KEY UPDATE `value` = VALUES(`value`);

SET FOREIGN_KEY_CHECKS = 1;
